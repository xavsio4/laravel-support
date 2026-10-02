<?php

namespace FifteenPeas\Support\Tests\Feature;

use FifteenPeas\Support\Ai\DocsAnswerer;
use FifteenPeas\Support\Models\Conversation;
use FifteenPeas\Support\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class EscalationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('support:index');
        $this->app->instance(DocsAnswerer::class, FakeAnswerer::uncited());
    }

    public function test_escalating_opens_a_freescout_conversation_with_the_transcript(): void
    {
        Http::fake([
            'support.example.test/api/conversations' => Http::response('', 201, ['Resource-ID' => '987']),
            'support.example.test/api/conversations/987/tags' => Http::response('', 204),
        ]);

        $user = $this->user();
        $id = $this->actingAs($user)->postJson('/support/api/messages', [
            'content' => 'My <widget> is gone',
            'page_url' => 'https://acme.test/sites/1',
        ])->json('conversation.id');

        $this->actingAs($user)->postJson("/support/api/conversations/{$id}/escalate")->assertOk();

        $conversation = Conversation::where('public_id', $id)->first();
        $this->assertSame('escalated', $conversation->status);
        $this->assertSame(987, $conversation->freescout_conversation_id);

        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://support.example.test/api/conversations') {
                return false;
            }

            $this->assertSame('fs-key', $request->header('X-FreeScout-API-Key')[0]);
            $this->assertSame(3, $request['mailboxId']);
            $this->assertSame('[Acme] My <widget> is gone', $request['subject']);
            $this->assertSame(['email' => 'ada@example.test', 'firstName' => 'Ada'], $request['customer']);
            $this->assertSame('customer', $request['threads'][0]['type']);
            $this->assertSame('My &lt;widget&gt; is gone', $request['threads'][0]['text']);
            $this->assertSame('note', $request['threads'][1]['type']);
            $this->assertSame(1, $request['threads'][1]['user']);
            $this->assertStringContainsString('Page: https://acme.test/sites/1', $request['threads'][1]['text']);
            $this->assertStringContainsString('Assistant (declined, not shown)', $request['threads'][1]['text']);

            return true;
        });

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/987/tags') && $r['tags'] === ['acme']);
    }

    public function test_escalating_twice_opens_one_freescout_conversation(): void
    {
        Http::fake(['*' => Http::response('', 201, ['Resource-ID' => '5'])]);
        $user = $this->user();
        $id = $this->actingAs($user)->postJson('/support/api/messages', ['content' => 'Help'])->json('conversation.id');

        $this->actingAs($user)->postJson("/support/api/conversations/{$id}/escalate");
        $this->actingAs($user)->postJson("/support/api/conversations/{$id}/escalate");

        Http::assertSentCount(2); // one create, one tag
    }

    public function test_without_an_agent_id_the_transcript_rides_under_the_question(): void
    {
        config(['support.freescout.user_id' => null]);
        Http::fake(['*' => Http::response('', 201, ['Resource-ID' => '5'])]);
        $user = $this->user();
        $id = $this->actingAs($user)->postJson('/support/api/messages', ['content' => 'Help'])->json('conversation.id');

        $this->actingAs($user)->postJson("/support/api/conversations/{$id}/escalate");

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/conversations')
            && count($r['threads']) === 1
            && str_contains($r['threads'][0]['text'], 'Escalated from the in-app assistant'));
    }
}
