<?php

namespace FifteenPeas\Support\Mcp\Tools;

use FifteenPeas\Support\Models\Document;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get one documentation page in full, as markdown, by its slug (from search_docs or list_docs).')]
#[IsReadOnly]
#[IsIdempotent]
class GetDoc extends Tool
{
    protected string $name = 'get_doc';

    public function handle(Request $request): Response
    {
        $slug = $request->validate(['slug' => ['required', 'string', 'max:200']])['slug'];

        $document = Document::where('slug', $slug)->first();

        if ($document === null) {
            return Response::error("No page with slug \"{$slug}\". Use list_docs to see the slugs.");
        }

        $source = $document->publicUrl() ? "\n\nSource: {$document->publicUrl()}" : '';

        return Response::text($document->content.$source);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()->description('The page slug, e.g. "getting-started".')->required(),
        ];
    }
}
