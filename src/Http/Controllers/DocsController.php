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
 * The widget's Docs and FAQ tabs. Public, like the docs themselves. Pages are
 * read on the public help pages, in their own window; the widget only lists
 * and searches them.
 */
class DocsController extends Controller
{
    public function index(): JsonResponse
    {
        $docs = Document::query()
            ->where('slug', '!=', config('support.faq_slug'))
            ->orderBy('title')
            ->get()
            ->map(fn (Document $d) => [
                'slug' => $d->slug,
                'title' => $d->title,
                'description' => $d->summary(),
                'url' => $d->publicUrl(),
            ]);

        return $this->json([
            'docs' => $docs,
            'home' => config('support.public.enabled') ? route('support.public.home') : null,
            'has_faq' => app(Faq::class)->document() !== null,
        ]);
    }

    public function search(Request $request, DocsSearch $search): JsonResponse
    {
        $query = mb_substr((string) $request->query('q', ''), 0, 200);

        $results = collect($search->search($query, 8))->map(fn (array $h) => [
            'slug' => $h['slug'],
            'title' => $h['title'],
            'section' => $h['section'],
            'snippet' => $h['snippet'],
            'url' => $h['url'],
        ]);

        return $this->json(['results' => $results]);
    }

    public function faq(Faq $faq, DocRenderer $renderer): JsonResponse
    {
        $toPage = fn (string $slug) => Document::where('slug', $slug)->first()?->publicUrl() ?? '#';

        $entries = array_map(fn (array $e) => [
            'question' => $e['question'],
            'html' => $renderer->markdown($e['answer'], $toPage),
        ], $faq->entries());

        return $this->json(['faq' => $entries]);
    }

    /** @param  array<string, mixed>  $data */
    private function json(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'public, max-age=300');
    }
}
