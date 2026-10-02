<?php

namespace FifteenPeas\Support\Mcp;

use FifteenPeas\Support\Mcp\Tools\GetDoc;
use FifteenPeas\Support\Mcp\Tools\ListDocs;
use FifteenPeas\Support\Mcp\Tools\SearchDocs;
use Laravel\Mcp\Server;

/**
 * Public, read-only MCP server over the app's support docs: the same corpus
 * the in-app assistant answers from, for people asking their own assistant.
 */
class DocsServer extends Server
{
    protected string $version = '1.0.0';

    protected array $tools = [
        ListDocs::class,
        SearchDocs::class,
        GetDoc::class,
    ];

    protected function boot(): void
    {
        $app = config('support.app_name');

        $this->name = "{$app} documentation";
        $this->instructions = <<<TEXT
        The official user documentation for {$app}. Use search_docs to find the
        section that answers a question, then get_doc for the full page when the
        snippet is not enough. list_docs shows every page. Answer from these
        pages and link to them; if they do not cover a question, say so rather
        than guessing how {$app} behaves.
        TEXT;
    }
}
