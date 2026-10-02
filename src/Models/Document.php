<?php

namespace FifteenPeas\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One markdown file from the app's docs folder, as last indexed.
 *
 * @property string $slug
 * @property string $title
 * @property string|null $description
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

    /**
     * Where a person or a crawler can read this page: its own url from the
     * front-matter, else the package's public markdown copy, else nowhere.
     */
    public function publicUrl(): ?string
    {
        if ($this->url) {
            return $this->url;
        }

        return config('support.public.enabled') ? route('support.public.doc', ['slug' => $this->slug]) : null;
    }

    /** The front-matter description, else the first paragraph of prose. */
    public function summary(int $limit = 160): string
    {
        if (filled($this->description)) {
            return Str::limit($this->description, $limit);
        }

        foreach (preg_split('/\R{2,}/', $this->content) as $block) {
            $block = trim($block);

            if ($block !== '' && ! preg_match('/^(#|```|[-*|>]|\d+\.)/', $block)) {
                return Str::limit(preg_replace('/\s+/', ' ', strip_tags($block)), $limit);
            }
        }

        return '';
    }
}
