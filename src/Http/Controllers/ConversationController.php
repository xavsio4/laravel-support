<?php

namespace FifteenPeas\Support\Http\Controllers;

use FifteenPeas\Support\Http\Controllers\Concerns\ResolvesSupportUser;
use FifteenPeas\Support\Http\MessagePresenter;
use FifteenPeas\Support\Jobs\EscalateConversation;
use FifteenPeas\Support\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ConversationController extends Controller
{
    use ResolvesSupportUser;

    /** The user's open conversation, so the widget resumes where they left off. */
    public function current(Request $request): JsonResponse
    {
        $conversation = Conversation::query()
            ->where('user_id', $this->supportUser($request)['id'])
            ->where('status', '!=', Conversation::STATUS_CLOSED)
            ->latest('id')
            ->first();

        return response()->json([
            'conversation' => $conversation
                ? MessagePresenter::conversation($conversation, $conversation->messages()->get())
                : null,
        ]);
    }

    /** Messages after ?after=, polled while an answer is pending. */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->ensureOwns($request, $conversation);

        $messages = $conversation->messages()
            ->where('id', '>', (int) $request->query('after', 0))
            ->get();

        return response()->json(['conversation' => MessagePresenter::conversation($conversation, $messages)]);
    }

    /** Hands the conversation to a person, in FreeScout. */
    public function escalate(Request $request, Conversation $conversation): JsonResponse
    {
        $this->ensureOwns($request, $conversation);

        if ($conversation->status === Conversation::STATUS_AI) {
            // Marked first, queued second: a missing worker leaves escalated
            // rows with no FreeScout id, which is findable, instead of nothing.
            $conversation->update(['status' => Conversation::STATUS_ESCALATED, 'escalated_at' => now()]);
            EscalateConversation::dispatch($conversation);
        }

        return response()->json(['conversation' => MessagePresenter::conversation($conversation, [])]);
    }

    /** Ends the conversation from the user's side, so the next question starts fresh. */
    public function close(Request $request, Conversation $conversation): JsonResponse
    {
        $this->ensureOwns($request, $conversation);

        // An escalated conversation stays open for the agent's reply; it is
        // closed from FreeScout. The widget just starts a new one.
        if ($conversation->status === Conversation::STATUS_AI) {
            $conversation->update(['status' => Conversation::STATUS_CLOSED]);
        }

        return response()->json(['ok' => true]);
    }
}
