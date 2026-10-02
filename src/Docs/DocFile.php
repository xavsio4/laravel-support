<?php

namespace FifteenPeas\Support\Docs;

/** A markdown file read from disk, before it is indexed. */
final readonly class DocFile
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $url,
        public string $content,
    ) {}

    public function checksum(): string
    {
        return hash('sha256', $this->title."\n".$this->url."\n".$this->content);
    }

    /**
     * A rough count, about four characters a token. Good enough for a budget
     * warning; the API reports the real figure in usage.
     */
    public function tokens(): int
    {
        return (int) ceil(mb_strlen($this->title.$this->content) / 4);
    }
}
