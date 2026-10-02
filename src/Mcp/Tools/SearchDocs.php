<?php

namespace FifteenPeas\Support\Mcp\Tools;

use FifteenPeas\Support\Docs\DocsSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Search the documentation by keywords. Returns the best-matching sections with the page slug, a snippet and a link.')]
#[IsReadOnly]
#[IsIdempotent]
class SearchDocs extends Tool
{
    protected string $name = 'search_docs';

    public function handle(Request $request, DocsSearch $search): Response
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:300'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $hits = $search->search($data['query'], $data['limit'] ?? 5);

        if ($hits === []) {
            return Response::text('No section matches. Try other words, or list_docs to see every page.');
        }

        $text = collect($hits)->map(fn (array $h) => implode("\n", array_filter([
            "## {$h['title']}".($h['section'] !== '' ? " › {$h['section']}" : ''),
            "slug: {$h['slug']}",
            $h['url'] ? "url: {$h['url']}" : null,
            $h['snippet'],
        ])))->implode("\n\n");

        return Response::text($text);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Keywords or a question, in any language the docs use.')->required(),
            'limit' => $schema->integer()->description('How many sections to return, 1 to 10. Default 5.'),
        ];
    }
}
