<?php

namespace FifteenPeas\Support\Tests\Unit;

use FifteenPeas\Support\Docs\DocsRepository;
use PHPUnit\Framework\TestCase;

class DocsRepositoryTest extends TestCase
{
    public function test_it_reads_front_matter_and_falls_back_to_path_and_heading(): void
    {
        $docs = (new DocsRepository(__DIR__.'/../fixtures/docs'))->all();

        $this->assertSame(['getting-started', 'integrations-slack', 'team', 'troubleshooting'], array_map(fn ($d) => $d->slug, $docs));

        $this->assertSame('Getting started', $docs[0]->title);
        $this->assertSame('https://acme.test/docs/getting-started', $docs[0]->url);
        $this->assertStringStartsWith('# Getting started', $docs[0]->content);

        $this->assertSame('Slack notifications', $docs[1]->title);
        $this->assertNull($docs[1]->url);
        $this->assertSame('Create a site and install the snippet.', $docs[0]->description);
        $this->assertNull($docs[1]->description);
    }

    public function test_front_matter_slug_wins(): void
    {
        $doc = (new DocsRepository('/unused'))->parse('a/b.md', "---\nslug: custom\n---\nBody");

        $this->assertSame('custom', $doc->slug);
        $this->assertSame('A B', $doc->title);
        $this->assertSame('Body', $doc->content);
    }

    public function test_bad_front_matter_names_the_file(): void
    {
        $this->expectExceptionMessage('Invalid front-matter in a/b.md');

        (new DocsRepository('/unused'))->parse('a/b.md', "---\ndescription: Setup: the steps\n---\nBody");
    }
}
