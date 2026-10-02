<?php

namespace FifteenPeas\Support\Tests\Feature;

use FifteenPeas\Support\Tests\TestCase;

class PublicDocsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('support:index');
    }

    public function test_llms_txt_follows_the_llmstxt_shape(): void
    {
        config(['support.public.summary' => 'Feedback widgets for web apps.']);

        // As an agent asks: without text/html in Accept.
        $body = $this->get('/llms.txt', ['Accept' => '*/*'])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
            ->getContent();

        $this->assertStringStartsWith("# Acme\n\n> Feedback widgets for web apps.\n", $body);
        $this->assertStringContainsString("## Docs\n\n- [", $body);
        $this->assertStringContainsString("\n- [Getting started](http://localhost/help/getting-started.md): Create a site and install the snippet.\n", $body);
        $this->assertStringContainsString('- [Slack notifications](http://localhost/help/integrations-slack.md)', $body);
        $this->assertStringContainsString('- [All pages in one file](http://localhost/llms-full.txt)', $body);
        $this->assertStringContainsString('[MCP server](http://localhost/mcp/docs)', $body);
    }

    public function test_llms_full_txt_holds_every_page_once_titled(): void
    {
        $body = $this->get('/llms-full.txt')->assertOk()->getContent();

        $this->assertSame(1, substr_count($body, '# Getting started'));
        $this->assertStringContainsString('Source: http://localhost/help/integrations-slack.md', $body);
        $this->assertStringContainsString('Connect Slack from Settings > Integrations', $body);
    }

    public function test_each_page_is_served_as_markdown(): void
    {
        $this->get('/help/integrations-slack.md')
            ->assertOk()
            ->assertSee("# Slack notifications\n\nConnect Slack", false);

        $this->get('/help/nope.md')->assertNotFound();
    }

    public function test_browsers_get_plain_text_so_the_page_displays(): void
    {
        $this->get('/help/integrations-slack.md', ['Accept' => 'text/html,application/xhtml+xml,*/*;q=0.8'])
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

        $this->get('/help/integrations-slack.md', ['Accept' => '*/*'])
            ->assertHeader('Content-Type', 'text/markdown; charset=utf-8');
    }

    public function test_citations_fall_back_to_the_public_page(): void
    {
        $doc = \FifteenPeas\Support\Models\Document::where('slug', 'integrations-slack')->first();
        $this->assertSame('http://localhost/help/integrations-slack', $doc->publicUrl());

        $withUrl = \FifteenPeas\Support\Models\Document::where('slug', 'getting-started')->first();
        $this->assertSame('https://acme.test/docs/getting-started', $withUrl->publicUrl());
    }
}
