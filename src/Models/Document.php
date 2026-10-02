<?php

namespace FifteenPeas\Support\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One markdown file from the app's docs folder, as last indexed.
 *
 * @property string $slug
 * @property string $title
 * @property string|null $url
 * @property string $content
 * @property string $checksum
 * @property int $tokens
 */
class Document extends Model
{
    protected $table = 'support_documents';

    protected $guarded = [];

    protected $casts = [
        'tokens' => 'integer',
    ];
}
