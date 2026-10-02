<?php

namespace FifteenPeas\Support\Jobs;

use FifteenPeas\Support\FreeScout\FreeScoutClient;
use FifteenPeas\Support\Models\Conversation;
use FifteenPeas\Support\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/**
 * Opens the FreeScout conversation for an escalated chat. The conversation is
 * marked escalated before this is queued, so a stalled worker shows up as
 * escalated chats with no FreeScout id rather than as nothing at all.
 */
class EscalateConversation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public Conversation $conversation)
    {
        $this->onQueue(config('support.queue'));
    }

    public function handle(FreeScoutClient $freescout): void
    {
        $conversation = $this->conversation->fresh();

        if ($conversation === null || $conversation->freescout_conversation_id !== null) {
            return;
        }

        $messages = $conversation->messages()->get();
        $question = $messages->where('role', Message::ROLE_USER)->last();
        $app = config('support.app_name');

        $id = $freescout->createConversation([
            'type' => 'email',
            'mailboxId' => config('support.freescout.mailbox_id'),
            'subject' => Str::limit("[{$app}] ".($question?->content ?? 'Support request'), 120),
            'customer' => array_filter([
                'email' => $conversation->user_email,
                'firstName' => $conversation->user_name,
            ]),
            'status' => 'active',
            'threads' => $this->threads($conversation, $question, $messages->all()),
        ]);

        $conversation->update(['freescout_conversation_id' => $id]);

        if (filled($tag = config('support.freescout.tag'))) {
            try {
                $freescout->tagConversation($id, [Str::slug($tag)]);
            } catch (Throwable $e) {
                // The conversation exists; a missing tag is not worth a retry
                // that would open a second one.
                report($e);
            }
        }
    }

    /**
     * The question as the customer's thread and the transcript as a private
     * note. FreeScout only takes a note from an agent, so without a
     * FREESCOUT_USER_ID the transcript rides along under the question.
     *
     * @param  array<int, Message>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function threads(Conversation $conversation, ?Message $question, array $messages): array
    {
        $text = $this->html($question?->content ?? 'The user asked to talk to a person.');
        $transcript = $this->transcript($conversation, $messages);
        $agent = config('support.freescout.user_id');

        if ($agent === null) {
            return [[
                'type' => 'customer',
                'customer' => ['email' => $conversation->user_email],
                'text' => $text.'<br><br><hr>'.$transcript,
            ]];
        }

        return [
            ['type' => 'customer', 'customer' => ['email' => $conversation->user_email], 'text' => $text],
            ['type' => 'note', 'user' => $agent, 'text' => $transcript],
        ];
    }

    /**
     * @param  array<int, Message>  $messages
     */
    private function transcript(Conversation $conversation, array $messages): string
    {
        $lines = ['<b>Escalated from the in-app assistant</b>'];

        if ($conversation->page_url) {
            $lines[] = 'Page: '.e($conversation->page_url);
        }

        $lines[] = 'User id: '.e($conversation->user_id);
        $lines[] = '';

        foreach ($messages as $message) {
            $who = match ($message->role) {
                Message::ROLE_USER => 'User',
                Message::ROLE_ASSISTANT => $message->declined ? 'Assistant (declined, not shown)' : 'Assistant',
                default => 'Agent',
            };

            $text = $message->content ?? '';

            if ($message->role === Message::ROLE_ASSISTANT && $message->citations) {
                $sources = collect($message->citations)->map(fn ($c) => "[{$c['n']}] {$c['title']}")->implode(', ');
                $text .= "\nSources: {$sources}";
            }

            $lines[] = "<b>{$who}:</b> ".$this->html($text);
        }

        return implode('<br>', $lines);
    }

    private function html(string $text): string
    {
        return nl2br(e($text), false);
    }

    public function failed(Throwable $e): void
    {
        report($e);
    }
}
