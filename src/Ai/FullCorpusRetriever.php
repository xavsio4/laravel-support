<?php

namespace FifteenPeas\Support\Ai;

use FifteenPeas\Support\Models\Document;

/**
 * Every document, every time. App support docs are small, and the model
 * reading all of them is both the most accurate answer and the tightest
 * boundary. The fixed order keeps the cached prefix identical across
 * questions, so after the first one the corpus is billed at cache rates.
 */
class FullCorpusRetriever implements Retriever
{
    public function documentsFor(string $question): array
    {
        return Document::query()->orderBy('slug')->get()->all();
    }
}
