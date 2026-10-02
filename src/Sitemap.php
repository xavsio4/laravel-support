<?php

namespace FifteenPeas\Support;

use FifteenPeas\Support\Models\Document;

/**
 * The public help pages, for the app's own sitemap:
 *
 *     foreach (\FifteenPeas\Support\Sitemap::urls() as $url) { ... }
 */
class Sitemap
{
    /** @return array<int, array{loc: string, lastmod: string|null}> */
    public static function urls(): array
    {
        if (! config('support.public.enabled')) {
            return [];
        }

        $urls = [['loc' => route('support.public.home'), 'lastmod' => Document::max('updated_at')]];

        foreach (Document::query()->orderBy('slug')->get() as $document) {
            $urls[] = [
                'loc' => route('support.public.page', ['slug' => $document->slug]),
                'lastmod' => $document->updated_at?->toAtomString(),
            ];
        }

        $urls[0]['lastmod'] = $urls[0]['lastmod'] ? \Illuminate\Support\Carbon::parse($urls[0]['lastmod'])->toAtomString() : null;

        return $urls;
    }
}
