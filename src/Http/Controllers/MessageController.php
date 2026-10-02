<?php

namespace FifteenPeas\Support\Http\Controllers;

use FifteenPeas\Support\Http\Controllers\Concerns\ResolvesSupportUser;
use FifteenPeas\Support\Http\MessagePresenter;
use FifteenPeas\Support\Jobs\AnswerMessage;
use FifteenPeas\Support\Models\Conversation;
use FifteenPeas\Support\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    use ResolvesSupportUser;

    /**
     * Stores the question and a pending answer, then queues the answer. The
     * rows exist before the job does, so a dead worker shows up as pending
     * answers that time out, never as a question that vanished.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:'.config('support.limits.message_max_chars')],
            'conversation_id' => ['nullable', 'string'],
            'page_url' => ['nullable', 'string', 'max:2000'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $user = $this->supportUser($request);

        $conversation = null;

        if (! empty($data['conversation_id'])) {
            $conversation = Conversation::where('public_id', $data['conversation_id'])->first();
            abort_if($conversation === null, 404);
            $this->ensureOwns($request, $conversation);
        }

        if ($conversation?->status === Conversation::STATUS_CLOSED) {
            $conversation = null;
        }

        if ($conversation?->isEscalated()) {
            return response()->json([
                'message' => 'This conversation is with the support team; replies continue by email.',
            ], 409);
        }

        if ($conversation && $conversation->messages()->where('role', Message::ROLE_USER)->count()
            >= config('support.limits.messages_per_conversation')) {
            return response()->json(['message' => 'This conversation is too long. Start a new one.'], 422);
        }

        [$conversation, $reply] = DB::transaction(function () use ($conversation, $data, $user) {
            $conversation ??= Conversation::create([
                'user_id' => $user['id'],
                'user_email' => $user['email'],
                'user_name' => $user['name'],
                'locale' => $data['locale'] ?? null,
                'page_url' => $data['page_url'] ?? null,
                'status' => Conversation::STATUS_AI,
            ]);

            $question = $conversation->messages()->create([
                'role' => Message::ROLE_USER,
                'content' => trim($data['content']),
                'status' => Message::STATUS_DONE,
            ]);

            $reply = $conversation->messages()->create([
                'role' => Message::ROLE_ASSISTANT,
                'status' => Message::STATUS_PENDING,
            ]);

            return [$conversation, [$question, $reply]];
        });

        // After the commit: a worker picking the job up mid-transaction would
        // not find the row.
        AnswerMessage::dispatch($reply[1]);

        return response()->json([
            'conversation' => MessagePresenter::conversation($conversation, $reply),
        ], 201);
    }
}
