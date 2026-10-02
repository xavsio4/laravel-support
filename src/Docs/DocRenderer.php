<?php

namespace FifteenPeas\Support\Docs;

use FifteenPeas\Support\Models\Document;
use Illuminate\Support\Str;

/**
 * Markdown to HTML for the widget's reader and the public pages. Raw HTML in
 * the docs is escaped and unsafe links are dropped, so the output can go into
 * the widget's shadow root as is.
 */
class DocRenderer
{
    /**
     * @param  callable(string): string  $linkToDoc  turns a slug into an href
     */
    public function html(Document $document, callable $linkToDoc): string
    {
        return $this->markdown($this->body($document), $linkToDoc);
    }

    /** @param  callable(string): string  $linkToDoc */
    public function markdown(string $markdown, callable $linkToDoc): string
    {
        $html = Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        // Links between pages are written as "sites.md" so they also work on
        // GitHub; the reader and the HTML pages each resolve them their way.
        return preg_replace_callback(
            '/href="(?:\.\/)?([a-z0-9][a-z0-9_-]*)\.md(#[^"]*)?"/i',
            fn ($m) => 'href="'.e($linkToDoc($m[1])).($m[2] ?? '').'"',
            $html,
        );
    }

    /** The page without its leading "# Title", which every view shows itself. */
    public function body(Document $document): string
    {
        return ltrim(preg_replace('/\A#\s+.+\R+/', '', $document->content));
    }
}
