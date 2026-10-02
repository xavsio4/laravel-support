<?php

namespace FifteenPeas\Support\Docs;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads the app's support docs: every .md file under the docs path, with
 * optional YAML front-matter for title, slug and url.
 */
class DocsRepository
{
    public function __construct(private readonly string $path) {}

    /**
     * Sorted by slug, so the corpus is sent in the same order every time and
     * the prompt cache keeps hitting.
     *
     * @return array<int, DocFile>
     */
    public function all(): array
    {
        if (! is_dir($this->path)) {
            throw new RuntimeException("Support docs folder not found: {$this->path}");
        }

        $docs = [];

        foreach (Finder::create()->files()->in($this->path)->name('*.md') as $file) {
            $doc = $this->parse($file->getRelativePathname(), $file->getContents());
            $docs[$doc->slug] = $doc;
        }

        ksort($docs);

        return array_values($docs);
    }

    public function parse(string $relativePath, string $raw): DocFile
    {
        $meta = [];
        $body = $raw;

        if (preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $raw, $m)) {
            $meta = (array) Yaml::parse($m[1]);
            $body = $m[2];
        }

        $body = trim($body);
        $fallbackSlug = Str::slug(str_replace(['/', '\\'], '-', Str::beforeLast($relativePath, '.md')));

        return new DocFile(
            slug: (string) ($meta['slug'] ?? $fallbackSlug),
            title: (string) ($meta['title'] ?? $this->firstHeading($body) ?? Str::headline($fallbackSlug)),
            url: isset($meta['url']) ? (string) $meta['url'] : null,
            content: $body,
        );
    }

    private function firstHeading(string $body): ?string
    {
        return preg_match('/^#\s+(.+)$/m', $body, $m) ? trim($m[1]) : null;
    }
}
