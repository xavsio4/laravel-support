<?php

namespace FifteenPeas\Support\Tests\Feature;

use FifteenPeas\Support\Ai\Answer;
use FifteenPeas\Support\Ai\DocsAnswerer;

class FakeAnswerer implements DocsAnswerer
{
    /** @var array<int, array{documents: array, history: array, question: string}> */
    public array $calls = [];

    public function __construct(public Answer $answer) {}

    public function answer(array $documents, array $history, string $question): Answer
    {
        $this->calls[] = compact('documents', 'history', 'question');

        return $this->answer;
    }

    public static function cited(string $text = 'Use the dashboard[1].'): self
    {
        return new self(new Answer($text, [
            ['n' => 1, 'slug' => 'getting-started', 'title' => 'Getting started', 'url' => 'https://acme.test/docs/getting-started', 'cited_text' => 'Create a site'],
        ], ['input_tokens' => 1], 'end_turn'));
    }

    public static function uncited(string $text = 'Here is a poem about the sea.'): self
    {
        return new self(new Answer($text, [], ['input_tokens' => 1], 'end_turn'));
    }
}
