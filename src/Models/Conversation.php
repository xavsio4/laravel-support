<?php

namespace FifteenPeas\Support\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property string $user_id
 * @property string $user_email
 * @property string|null $user_name
 * @property string|null $locale
 * @property string|null $page_url
 * @property string $status
 * @property int|null $freescout_conversation_id
 * @property \Illuminate\Support\Carbon|null $escalated_at
 */
class Conversation extends Model
{
    use HasUlids;

    public const STATUS_AI = 'ai';

    public const STATUS_ESCALATED = 'escalated';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'support_conversations';

    protected $guarded = [];

    protected $casts = [
        'freescout_conversation_id' => 'integer',
        'escalated_at' => 'datetime',
    ];

    /** The ULID is the public handle; the integer id never leaves the server. */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function isEscalated(): bool
    {
        return $this->status !== self::STATUS_AI;
    }
}
