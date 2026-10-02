<?php

namespace FifteenPeas\Support\Http\Controllers;

use FifteenPeas\Support\Docs\DocRenderer;
use FifteenPeas\Support\Docs\DocsSearch;
use FifteenPeas\Support\Docs\Faq;
use FifteenPeas\Support\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * The widget's Docs and FAQ tabs. Public, like the docs themselves; pages are
 * rendered here so the widget does not carry a markdown renderer.
 */
class DocsController extends Controller
{
    /** Links between pages open in the widget's reader, not the browser. */
    private const READER_LINK = '#support-doc=';

    public function index(): JsonResponse
    {
        $docs = Document::query()
            ->where('slug', '!=', config('support.faq_slug'))
            ->orderBy('title')
            ->get()
            ->map(fn (Document $d) => ['slug' => $d->slug, 'title' => $d->title, 'description' => $d->summary()]);

        return $this->json(['docs' => $docs, 'has_faq' => app(Faq::class)->document() !== null]);
    }

    public function show(string $slug, DocRenderer $renderer): JsonResponse
    {
        $document = Document::where('slug', $slug)->firstOrFail();

        return $this->json(['doc' => [
            'slug' => $document->slug,
            'title' => $document->title,
            'html' => $renderer->html($document, fn ($s) => self::READER_LINK.$s),
            'url' => $document->publicUrl(),
        ]]);
    }

    public function search(Request $request, DocsSearch $search): JsonResponse
    {
        $query = mb_substr((string) $request->query('q', ''), 0, 200);

        $results = collect($search->search($query, 8))->map(fn (array $h) => [
            'slug' => $h['slug'],
            'title' => $h['title'],
            'section' => $h['section'],
            'snippet' => $h['snippet'],
        ]);

        return $this->json(['results' => $results]);
    }

    public function faq(Faq $faq, DocRenderer $renderer): JsonResponse
    {
        $entries = array_map(fn (array $e) => [
            'question' => $e['question'],
            'html' => $renderer->markdown($e['answer'], fn ($s) => self::READER_LINK.$s),
        ], $faq->entries());

        return $this->json(['faq' => $entries]);
    }

    /** @param  array<string, mixed>  $data */
    private function json(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'public, max-age=300');
    }
}
