<?php

namespace FifteenPeas\Support\Public;

use FifteenPeas\Support\Models\Document;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * The docs as plain files: what llms.txt links to, what an agent fetches, and
 * what the assistant's citation chips open when a page has no url of its own.
 */
class PublicDocsController extends Controller
{
    public function doc(string $slug): Response
    {
        $document = Document::where('slug', $slug)->firstOrFail();

        return $this->markdown("# {$document->title}\n\n".$this->withoutLeadingTitle($document));
    }

    /** llmstxt.org: a title, a one-paragraph summary, then annotated links. */
    public function index(): Response
    {
        $app = config('support.app_name');
        $summary = config('support.public.summary') ?: "User documentation for {$app}.";

        $lines = ["# {$app}", '', "> {$summary}", ''];
        $lines[] = "These pages describe what {$app} does today, including what it does not do yet. "
            ."They are the same pages {$app}'s in-app assistant answers from.";
        $lines[] = '';
        $lines[] = '## Docs';
        $lines[] = '';

        foreach ($this->documents() as $document) {
            $summary = $document->summary();
            $lines[] = "- [{$document->title}](".route('support.public.doc', ['slug' => $document->slug]).')'
                .($summary !== '' ? ": {$summary}" : '');
        }

        $lines[] = '';
        $lines[] = '## Optional';
        $lines[] = '';
        $lines[] = '- [All pages in one file]('.route('support.public.llms-full').')';

        if (config('support.mcp.enabled')) {
            $lines[] = '- [MCP server]('.url(config('support.mcp.path')).'): read-only, Streamable HTTP, tools `search_docs`, `get_doc`, `list_docs`';
        }

        return $this->markdown(implode("\n", $lines)."\n");
    }

    public function full(): Response
    {
        $app = config('support.app_name');

        $pages = $this->documents()->map(fn (Document $d) => "# {$d->title}\n\nSource: "
            .route('support.public.doc', ['slug' => $d->slug])."\n\n".$this->withoutLeadingTitle($d));

        return $this->markdown("# {$app} documentation\n\n".$pages->implode("\n\n---\n\n")."\n");
    }

    /** @return \Illuminate\Support\Collection<int, Document> */
    private function documents()
    {
        return Document::query()->orderBy('slug')->get();
    }

    /** Most pages open with their own "# Title"; the wrapper adds it once. */
    private function withoutLeadingTitle(Document $document): string
    {
        return ltrim(preg_replace('/\A#\s+.+\R+/', '', $document->content));
    }

    private function markdown(string $body): Response
    {
        // A browser (the citation chips, a person clicking a link in
        // llms.txt) may offer text/markdown as a download; text/plain is
        // displayed everywhere. Agents get the precise type.
        $type = str_contains((string) request()->header('Accept'), 'text/html') ? 'text/plain' : 'text/markdown';

        return response($body, 200, [
            'Content-Type' => $type.'; charset=utf-8',
            'Vary' => 'Accept',
            'Cache-Control' => 'public, max-age=3600',
            // Read by agents and browsers on other sites.
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
