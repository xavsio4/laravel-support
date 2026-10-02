<?php

namespace FifteenPeas\Support\Http;

use FifteenPeas\Support\Models\Conversation;
use FifteenPeas\Support\Models\Message;

/** The widget's view of a conversation. */
class MessagePresenter
{
    /**
     * @param  iterable<Message>  $messages
     * @return array<string, mixed>
     */
    public static function conversation(Conversation $conversation, iterable $messages): array
    {
        $list = [];

        foreach ($messages as $message) {
            $list[] = self::message($message);
        }

        return [
            'id' => $conversation->public_id,
            'status' => $conversation->status,
            'messages' => $list,
        ];
    }

    /** @return array<string, mixed> */
    public static function message(Message $message): array
    {
        $status = $message->effectiveStatus();

        return [
            'id' => $message->id,
            'role' => $message->role,
            'status' => $status,
            'declined' => $message->declined,
            // A declined answer's text came from outside the docs; the widget
            // shows its own localized "can't help with that" instead.
            'content' => $message->declined || $status !== Message::STATUS_DONE ? null : $message->content,
            'citations' => $message->declined ? [] : array_map(
                fn (array $c) => ['n' => $c['n'], 'title' => $c['title'], 'url' => $c['url']],
                $message->citations ?? [],
            ),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
