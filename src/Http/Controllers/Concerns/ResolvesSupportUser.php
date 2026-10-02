<?php

namespace FifteenPeas\Support\Http\Controllers\Concerns;

use FifteenPeas\Support\Models\Conversation;
use Illuminate\Http\Request;

trait ResolvesSupportUser
{
    /** @return array{id: string, name: ?string, email: string} */
    protected function supportUser(Request $request): array
    {
        return app(config('support.user_resolver'))($request->user());
    }

    /** Someone else's conversation is a 404, not a 403: it does not exist for you. */
    protected function ensureOwns(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->user_id === $this->supportUser($request)['id'], 404);
    }
}
