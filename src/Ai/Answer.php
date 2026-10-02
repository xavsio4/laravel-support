<?php

namespace FifteenPeas\Support\Ai;

final readonly class Answer
{
    /**
     * @param  array<int, array{n: int, slug: string, title: string, url: ?string, cited_text: string}>  $citations
     * @param  array<string, mixed>  $usage
     */
    public function __construct(
        public string $text,
        public array $citations,
        public array $usage,
        public ?string $stopReason,
        public bool $refused = false,
    ) {}
}
