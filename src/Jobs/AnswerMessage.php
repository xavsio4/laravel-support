<?php

namespace FifteenPeas\Support\Jobs;

use FifteenPeas\Support\Ai\DocsAnswerer;
use FifteenPeas\Support\Ai\Guardrail;
use FifteenPeas\Support\Ai\Retriever;
use FifteenPeas\Support\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Fills in a pending assistant message. Queued rather than answered in the
 * request: a model call takes seconds, and a php-fpm worker held for each
 * question is a dashboard that stops answering under modest traffic.
 */
class AnswerMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 5;

    public int $timeout = 90;

    public function __construct(public Message $reply)
    {
        $this->onQueue(config('support.queue'));
    }

    public function handle(Retriever $retriever, DocsAnswerer $answerer, Guardrail $guardrail): void
    {
        $reply = $this->reply->fresh();

        if ($reply === null || $reply->status !== Message::STATUS_PENDING) {
            return;
        }

        $earlier = Message::query()
            ->where('conversation_id', $reply->conversation_id)
            ->where('id', '<', $reply->id)
            ->whereIn('role', [Message::ROLE_USER, Message::ROLE_ASSISTANT])
            ->where('status', Message::STATUS_DONE)
            ->orderByDesc('id')
            ->limit(config('support.ai.history_turns') + 1)
            ->get()
            ->reverse()
            ->values();

        $question = $earlier->pop();

        if ($question === null || $question->role !== Message::ROLE_USER) {
            $reply->update(['status' => Message::STATUS_FAILED, 'error' => 'No question precedes this reply.']);

            return;
        }

        // A declined turn is left out of the history: its text was never
        // shown, and replaying it would teach the model nothing useful.
        $history = $earlier
            ->reject(fn (Message $m) => $m->declined)
            ->map(fn (Message $m) => ['role' => $m->role, 'content' => (string) $m->content])
            ->all();

        // The API needs the first turn to come from the user.
        while ($history !== [] && $history[array_key_first($history)]['role'] !== Message::ROLE_USER) {
            array_shift($history);
        }

        $documents = $retriever->documentsFor($question->content);

        if ($documents === []) {
            $reply->update(['status' => Message::STATUS_DONE, 'declined' => true, 'error' => 'No support docs indexed.']);

            return;
        }

        $answer = $answerer->answer($documents, array_values($history), $question->content);

        $reply->update([
            'status' => Message::STATUS_DONE,
            // Kept for review even when declined; the widget never shows it.
            'content' => $answer->text,
            'citations' => $answer->citations,
            'declined' => $guardrail->declines($answer),
            'usage' => $answer->usage + ['stop_reason' => $answer->stopReason],
        ]);
    }

    public function failed(Throwable $e): void
    {
        $this->reply->update([
            'status' => Message::STATUS_FAILED,
            'error' => mb_substr($e::class.': '.$e->getMessage(), 0, 1000),
        ]);
    }
}
