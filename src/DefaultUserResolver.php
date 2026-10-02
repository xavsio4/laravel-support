<?php

namespace FifteenPeas\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Reads id, name and email off the authenticated user. Apps whose user model
 * differs point support.user_resolver at their own invokable class.
 */
class DefaultUserResolver
{
    /** @return array{id: string, name: ?string, email: string} */
    public function __invoke(Authenticatable $user): array
    {
        return [
            'id' => (string) $user->getAuthIdentifier(),
            'name' => $user->name ?? null,
            'email' => (string) $user->email,
        ];
    }
}
