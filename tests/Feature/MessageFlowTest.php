<?php

namespace FifteenPeas\Support\Tests\Feature;

use FifteenPeas\Support\Ai\DocsAnswerer;
use FifteenPeas\Support\Jobs\AnswerMessage;
use FifteenPeas\Support\Models\Conversation;
use FifteenPeas\Support\Models\Message;
use FifteenPeas\Support\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

class MessageFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('support:index');
    }

    private function answerWith(FakeAnswerer $fake): FakeAnswerer
    {
        $this->app->instance(DocsAnswerer::class, $fake);

        return $fake;
    }

    public function test_a_question_gets_a_cited_answer(): void
    {
        $fake = $this->answerWith(FakeAnswerer::cited());
        $user = $this->user();

        $response = $this->actingAs($user)->postJson('/support/api/messages', [
            'content' => 'How do I add a site?',
            'page_url' => 'https://acme.test/dashboard',
        ])->assertCreated();

        $id = $response->json('conversation.id');
        $conversation = Conversation::where('public_id', $id)->firstOrFail();
        $this->assertSame((string) $user->id, $conversation->user_id);
        $this->assertSame('ada@example.test', $conversation->user_email);

        // The queue is sync here, so the answer is already in.
        $this->actingAs($user)->getJson("/support/api/conversations/{$id}")
            ->assertOk()
            ->assertJsonPath('conversation.messages.1.role', 'assistant')
            ->assertJsonPath('conversation.messages.1.status', 'done')
            ->assertJsonPath('conversation.messages.1.content', 'Use the dashboard[1].')
            ->assertJsonPath('conversation.messages.1.citations.0.url', 'https://acme.test/docs/getting-started')
            ->assertJsonMissingPath('conversation.messages.1.citations.0.cited_text');

        $this->assertCount(5, $fake->calls[0]['documents']);
        $this->assertSame('How do I add a site?', $fake->calls[0]['question']);
    }

    public function test_an_uncited_answer_is_declined_and_never_shown(): void
    {
        $this->answerWith(FakeAnswerer::uncited());
        $user = $this->user();

        $id = $this->actingAs($user)->postJson('/support/api/messages', ['content' => 'Write me a poem'])->json('conversation.id');

        $this->actingAs($user)->getJson("/support/api/conversations/{$id}")
            ->assertJsonPath('conversation.messages.1.declined', true)
            ->assertJsonPath('conversation.messages.1.content', null);

        // Kept for review.
        $this->assertSame('Here is a poem about the sea.', Message::where('role', 'assistant')->value('content'));
    }

    public function test_history_is_sent_without_declined_turns(): void
    {
        $fake = $this->answerWith(FakeAnswerer::uncited());
        $user = $this->user();

        $id = $this->actingAs($user)->postJson('/support/api/messages', ['content' => 'Poem?'])->json('conversation.id');

        $fake->answer = FakeAnswerer::cited()->answer;
        $this->actingAs($user)->postJson('/support/api/messages', ['content' => 'First question', 'conversation_id' => $id]);
        $this->actingAs($user)->postJson('/support/api/messages', ['content' => 'Second question', 'conversation_id' => $id]);

        // Turn 3 sees: "Poem?" (its declined reply dropped), "First question" and its answer.
        $this->assertSame([
            ['role' => 'user', 'content' => 'Poem?'],
            ['role' => 'user', 'content' => 'First question'],
            ['role' => 'assistant', 'content' => 'Use the dashboard[1].'],
        ], $fake->calls[2]['history']);
        $this->assertSame('Second question', $fake->calls[2]['question']);
    }

    public function test_rows_exist_before_the_job_runs_and_a_stale_pending_reads_as_failed(): void
    {
        Queue::fake();
        $user = $this->user();

        $id = $this->actingAs($user)->postJson('/support/api/messages', ['content' => 'Hello?'])
            ->assertJsonPath('conversation.messages.1.status', 'pending')
            ->json('conversation.id');

        Queue::assertPushed(AnswerMessage::class);

        $this->travel(3)->minutes();

        $this->actingAs($user)->getJson("/support/api/conversations/{$id}")
            ->assertJsonPath('conversation.messages.1.status', 'failed');
    }

    public function test_another_users_conversation_is_not_found(): void
    {
        $this->answerWith(FakeAnswerer::cited());
        $id = $this->actingAs($this->user())->postJson('/support/api/messages', ['content' => 'Hi'])->json('conversation.id');

        $other = $this->user('eve@example.test');

        $this->actingAs($other)->getJson("/support/api/conversations/{$id}")->assertNotFound();
        $this->actingAs($other)->postJson('/support/api/messages', ['content' => 'x', 'conversation_id' => $id])->assertNotFound();
        $this->actingAs($other)->postJson("/support/api/conversations/{$id}/escalate")->assertNotFound();
    }

    public function test_guests_are_turned_away(): void
    {
        $this->postJson('/support/api/messages', ['content' => 'Hi'])->assertUnauthorized();
    }

    public function test_an_escalated_conversation_takes_no_more_ai_questions(): void
    {
        Queue::fake();
        $user = $this->user();
        $id = $this->actingAs($user)->postJson('/support/api/messages', ['content' => 'Hi'])->json('conversation.id');

        $this->actingAs($user)->postJson("/support/api/conversations/{$id}/escalate")
            ->assertJsonPath('conversation.status', 'escalated');

        $this->actingAs($user)->postJson('/support/api/messages', ['content' => 'More', 'conversation_id' => $id])
            ->assertStatus(409);
    }

    public function test_the_current_conversation_resumes_and_closing_starts_fresh(): void
    {
        $this->answerWith(FakeAnswerer::cited());
        $user = $this->user();
        $id = $this->actingAs($user)->postJson('/support/api/messages', ['content' => 'Hi'])->json('conversation.id');

        $this->actingAs($user)->getJson('/support/api/conversation')->assertJsonPath('conversation.id', $id);

        $this->actingAs($user)->postJson("/support/api/conversations/{$id}/close")->assertOk();
        $this->actingAs($user)->getJson('/support/api/conversation')->assertJsonPath('conversation', null);

        // A message on the closed conversation opens a new one.
        $new = $this->actingAs($user)->postJson('/support/api/messages', ['content' => 'Again', 'conversation_id' => $id])->json('conversation.id');
        $this->assertNotSame($id, $new);
    }

    public function test_messages_are_rate_limited(): void
    {
        Queue::fake();
        config(['support.limits.messages_per_minute' => 2]);
        $user = $this->user();

        $this->actingAs($user)->postJson('/support/api/messages', ['content' => '1'])->assertCreated();
        $this->actingAs($user)->postJson('/support/api/messages', ['content' => '2'])->assertCreated();
        $this->actingAs($user)->postJson('/support/api/messages', ['content' => '3'])->assertTooManyRequests();
    }

    public function test_with_no_docs_the_reply_is_declined_without_calling_the_model(): void
    {
        $fake = $this->answerWith(FakeAnswerer::cited());
        \FifteenPeas\Support\Models\Document::query()->delete();
        $user = $this->user();

        $id = $this->actingAs($user)->postJson('/support/api/messages', ['content' => 'Hi'])->json('conversation.id');

        $this->actingAs($user)->getJson("/support/api/conversations/{$id}")->assertJsonPath('conversation.messages.1.declined', true);
        $this->assertSame([], $fake->calls);
    }
}
