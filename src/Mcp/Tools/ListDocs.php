<?php

namespace FifteenPeas\Support\Mcp\Tools;

use FifteenPeas\Support\Models\Document;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List every documentation page with its slug, title and a one-line summary.')]
#[IsReadOnly]
#[IsIdempotent]
class ListDocs extends Tool
{
    protected string $name = 'list_docs';

    public function handle(Request $request): Response
    {
        $lines = Document::query()->orderBy('slug')->get()
            ->map(fn (Document $d) => "- {$d->slug}: {$d->title}".(($s = $d->summary()) !== '' ? " — {$s}" : ''));

        return Response::text($lines->isEmpty() ? 'No documentation is published.' : $lines->implode("\n"));
    }
}
