<?php

namespace FifteenPeas\Support\Docs;

use FifteenPeas\Support\Models\Document;

/**
 * The FAQ is an ordinary docs page (slug from support.faq_slug): each "##"
 * heading is a question and what follows is its answer. Being a page, it is
 * also indexed, so the assistant and the MCP server answer from it too.
 */
class Faq
{
    public function document(): ?Document
    {
        return Document::where('slug', config('support.faq_slug'))->first();
    }

    /** @return array<int, array{question: string, answer: string}> markdown answers, questions without an answer skipped */
    public function entries(): array
    {
        $document = $this->document();

        if ($document === null) {
            return [];
        }

        $entries = [];
        $parts = preg_split('/^##\s+(.+)$/m', $document->content, -1, PREG_SPLIT_DELIM_CAPTURE);

        // [intro, question, answer, question, answer, ...]
        for ($i = 1; $i + 1 < count($parts); $i += 2) {
            $answer = trim($parts[$i + 1]);

            if ($answer !== '') {
                $entries[] = ['question' => trim($parts[$i]), 'answer' => $answer];
            }
        }

        return $entries;
    }
}
