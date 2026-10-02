<?php

namespace FifteenPeas\Support\Ai;

use FifteenPeas\Support\Models\Document;

interface DocsAnswerer
{
    /**
     * @param  array<int, Document>  $documents
     * @param  array<int, array{role: string, content: string}>  $history  earlier turns, oldest first
     */
    public function answer(array $documents, array $history, string $question): Answer;
}
