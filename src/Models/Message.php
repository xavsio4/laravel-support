<?php

namespace FifteenPeas\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property string $role
 * @property string|null $content
 * @property array<int, array<string, mixed>>|null $citations
 * @property string $status
 * @property bool $declined
 * @property array<string, mixed>|null $usage
 * @property string|null $error
 * @property int|null $freescout_thread_id
 * @property \Illuminate\Support\Carbon $created_at
 */
class Message extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_AGENT = 'agent';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $table = 'support_messages';

    protected $guarded = [];

    protected $casts = [
        'citations' => 'array',
        'usage' => 'array',
        'declined' => 'boolean',
        'freescout_thread_id' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * A pending answer past the timeout is reported as failed. The row is not
     * rewritten: the job may still land, and then the real answer wins.
     */
    public function effectiveStatus(): string
    {
        if ($this->status === self::STATUS_PENDING
            && $this->created_at->lt(now()->subSeconds(config('support.limits.pending_timeout_seconds')))) {
            return self::STATUS_FAILED;
        }

        return $this->status;
    }
}
