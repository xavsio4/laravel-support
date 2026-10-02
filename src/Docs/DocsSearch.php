<?php

namespace FifteenPeas\Support\Docs;

use FifteenPeas\Support\Models\Document;
use Illuminate\Support\Str;

/**
 * Keyword search over the docs, by section. Plain PHP rather than database
 * full-text search: a support corpus is a few dozen pages, this works the same
 * on any database, and a section (a heading and its text) is the unit an
 * assistant actually wants back.
 */
class DocsSearch
{
    private const STOPWORDS = [
        'the', 'and', 'for', 'how', 'can', 'what', 'why', 'does', 'with', 'you', 'your', 'are', 'not', 'this', 'that',
        'les', 'des', 'une', 'pour', 'comment', 'est', 'que', 'qui', 'dans', 'mon', 'mes',
        'der', 'die', 'das', 'und', 'wie', 'ich', 'ist', 'los', 'las', 'del', 'con', 'por', 'qué',
    ];

    /**
     * @return array<int, array{slug: string, title: string, section: string, snippet: string, url: string|null, score: int}>
     */
    public function search(string $query, int $limit = 5): array
    {
        $terms = $this->terms($query);

        if ($terms === []) {
            return [];
        }

        $phrase = mb_strtolower(trim($query));
        $hits = [];

        foreach (Document::query()->orderBy('slug')->get() as $document) {
            $title = mb_strtolower($document->title);

            foreach ($this->sections($document->content) as [$heading, $body]) {
                $headingLower = mb_strtolower($heading);
                $bodyLower = mb_strtolower($body);
                $score = 0;

                foreach ($terms as $term) {
                    $score += str_contains($title, $term) ? 4 : 0;
                    $score += str_contains($headingLower, $term) ? 6 : 0;
                    $score += min(substr_count($bodyLower, $term), 5);
                }

                if ($score === 0) {
                    continue;
                }

                if (mb_strlen($phrase) > 3 && str_contains($headingLower.' '.$bodyLower, $phrase)) {
                    $score += 10;
                }

                $hits[] = [
                    'slug' => $document->slug,
                    'title' => $document->title,
                    'section' => $heading,
                    'snippet' => $this->snippet($body, $terms),
                    'url' => $document->publicUrl(),
                    'score' => $score,
                ];
            }
        }

        usort($hits, fn ($a, $b) => $b['score'] <=> $a['score'] ?: strcmp($a['slug'], $b['slug']));

        return array_slice($hits, 0, max(1, $limit));
    }

    /** @return array<int, string> */
    private function terms(string $query): array
    {
        preg_match_all('/[\p{L}\p{N}_-]{3,}/u', mb_strtolower($query), $m);

        return array_values(array_unique(array_diff($m[0], self::STOPWORDS)));
    }

    /**
     * Splits on markdown headings; text before the first heading belongs to
     * the document itself.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function sections(string $content): array
    {
        $sections = [];
        $heading = '';
        $buffer = [];

        foreach (preg_split('/\R/', $content) as $line) {
            if (preg_match('/^#{1,6}\s+(.+)$/', $line, $m)) {
                if (trim(implode("\n", $buffer)) !== '' || $heading !== '') {
                    $sections[] = [$heading, trim(implode("\n", $buffer))];
                }
                $heading = trim($m[1]);
                $buffer = [];

                continue;
            }

            $buffer[] = $line;
        }

        $sections[] = [$heading, trim(implode("\n", $buffer))];

        return $sections;
    }

    /** @param  array<int, string>  $terms */
    private function snippet(string $body, array $terms): string
    {
        $flat = preg_replace('/\s+/', ' ', $body);
        $lower = mb_strtolower($flat);
        $at = 0;

        foreach ($terms as $term) {
            $pos = mb_strpos($lower, $term);
            if ($pos !== false) {
                $at = max(0, $pos - 80);
                break;
            }
        }

        return ($at > 0 ? '…' : '').Str::limit(mb_substr($flat, $at), 320);
    }
}
