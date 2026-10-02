<?php

namespace FifteenPeas\Support\Ai;

use FifteenPeas\Support\Models\Document;

/**
 * Picks the documents a question is answered from. Callers never assume the
 * whole corpus: an app that outgrows it swaps in a search-based retriever.
 */
interface Retriever
{
    /** @return array<int, Document> in a stable order */
    public function documentsFor(string $question): array;
}
