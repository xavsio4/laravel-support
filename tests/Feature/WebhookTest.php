<?php

namespace FifteenPeas\Support\Tests\Feature;

use FifteenPeas\Support\Models\Conversation;
use FifteenPeas\Support\Models\Message;
use FifteenPeas\Support\Tests\TestCase;

class WebhookTest extends TestCase
{
    private function conversation(): Conversation
    {
        return Conversation::create([
            'user_id' => '1', 'user_email' => 'ada@example.test', 'status' => 'escalated', 'freescout_conversation_id' => 987,
        ]);
    }

    private function send(string $event, array $payload, ?string $secret = 'hook-secret')
    {
        $body = json_encode($payload);
        $signature = base64_encode(hash_hmac('sha1', $body, (string) $secret, true));

        return $this->call('POST', '/support/api/webhooks/freescout', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FREESCOUT_EVENT' => $event,
            'HTTP_X_FREESCOUT_SIGNATURE' => $signature,
        ], $body);
    }

    private function replyPayload(array $threads): array
    {
        return ['id' => 987, 'status' => 'active', '_embedded' => ['threads' => $threads]];
    }

    public function test_a_bad_signature_is_rejected(): void
    {
        $this->send('convo.agent.reply.created', ['id' => 987], 'wrong')->assertUnauthorized();
    }

    public function test_agent_replies_are_stored_once_as_text(): void
    {
        $conversation = $this->conversation();
        $payload = $this->replyPayload([
            ['id' => 11, 'type' => 'customer', 'body' => 'Help'],
            ['id' => 12, 'type' => 'message', 'state' => 'published', 'body' => '<p>Hi Ada,</p><p>Fixed &amp; deployed.</p>'],
            ['id' => 13, 'type' => 'note', 'body' => 'internal'],
            ['id' => 14, 'type' => 'message', 'state' => 'draft', 'body' => 'draft'],
        ]);

        $this->send('convo.agent.reply.created', $payload)->assertNoContent();
        $this->send('convo.agent.reply.created', $payload)->assertNoContent();

        $replies = Message::where('conversation_id', $conversation->id)->get();
        $this->assertCount(1, $replies);
        $this->assertSame('agent', $replies[0]->role);
        $this->assertSame("Hi Ada,\nFixed & deployed.", $replies[0]->content);

        $this->actingAs($this->user())
            ->getJson("/support/api/conversations/{$conversation->public_id}")
            ->assertJsonPath('conversation.messages.0.content', "Hi Ada,\nFixed & deployed.");
    }

    public function test_status_changes_close_and_reopen(): void
    {
        $conversation = $this->conversation();

        $this->send('convo.status', ['id' => 987, 'status' => 'closed']);
        $this->assertSame('closed', $conversation->fresh()->status);

        $this->send('convo.status', ['id' => 987, 'status' => 'active']);
        $this->assertSame('escalated', $conversation->fresh()->status);
    }

    public function test_other_mailboxes_conversations_are_ignored(): void
    {
        $this->send('convo.agent.reply.created', $this->replyPayload([['id' => 1, 'type' => 'message', 'body' => 'x']]))
            ->assertNoContent();

        $this->assertSame(0, Message::count());
    }
}
