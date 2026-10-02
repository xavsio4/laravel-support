<?php

namespace FifteenPeas\Support\FreeScout;

use FifteenPeas\Support\Models\Conversation;
use FifteenPeas\Support\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Receives FreeScout's webhooks. FreeScout sends every mailbox's events to
 * every webhook URL, so a conversation we did not open is acknowledged and
 * ignored, not rejected.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = config('support.freescout.webhook_secret');
        abort_if(blank($secret), 404);

        // base64(HMAC-SHA1(raw body, secret)), per the API & Webhooks module.
        $expected = base64_encode(hash_hmac('sha1', $request->getContent(), $secret, true));
        abort_unless(hash_equals($expected, (string) $request->header('X-FreeScout-Signature')), 401);

        $event = (string) $request->header('X-FreeScout-Event');
        $payload = $request->json()->all();

        $conversation = isset($payload['id'])
            ? Conversation::where('freescout_conversation_id', (int) $payload['id'])->first()
            : null;

        if ($conversation === null) {
            return response()->noContent();
        }

        match ($event) {
            'convo.agent.reply.created' => $this->storeAgentReplies($conversation, $payload),
            'convo.status' => $this->syncStatus($conversation, $payload),
            default => null,
        };

        return response()->noContent();
    }

    /**
     * Stores every published agent reply not stored yet. Keyed on the thread
     * id, so a redelivered webhook, or one that arrives after a missed one,
     * neither duplicates nor drops a reply.
     *
     * @param  array<string, mixed>  $payload
     */
    private function storeAgentReplies(Conversation $conversation, array $payload): void
    {
        $threads = collect($payload['_embedded']['threads'] ?? [])
            ->filter(fn ($t) => ($t['type'] ?? null) === 'message' && ($t['state'] ?? 'published') === 'published')
            ->sortBy('id');

        DB::transaction(function () use ($conversation, $threads) {
            foreach ($threads as $thread) {
                Message::firstOrCreate(
                    ['freescout_thread_id' => (int) $thread['id']],
                    [
                        'conversation_id' => $conversation->id,
                        'role' => Message::ROLE_AGENT,
                        'content' => $this->plainText((string) ($thread['body'] ?? '')),
                        'status' => Message::STATUS_DONE,
                    ],
                );
            }
        });
    }

    /** @param  array<string, mixed>  $payload */
    private function syncStatus(Conversation $conversation, array $payload): void
    {
        $status = $payload['status'] ?? null;

        if ($status === 'closed' || $status === 'spam') {
            $conversation->update(['status' => Conversation::STATUS_CLOSED]);
        } elseif ($conversation->status === Conversation::STATUS_CLOSED) {
            // Reopened by the agent.
            $conversation->update(['status' => Conversation::STATUS_ESCALATED]);
        }
    }

    /**
     * Agent replies are HTML from FreeScout's editor. The widget renders text,
     * so line structure is kept and markup is dropped.
     */
    private function plainText(string $html): string
    {
        $text = preg_replace(['/<br\s*\/?>/i', '/<\/(p|div|li|h[1-6])>/i'], "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
