<?php

namespace FifteenPeas\Support\Tests\Feature;

use FifteenPeas\Support\Sitemap;
use FifteenPeas\Support\Tests\TestCase;

class HelpCentreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('support:index');
    }

    public function test_the_widget_lists_pages_without_the_faq(): void
    {
        $response = $this->getJson('/support/api/docs')->assertOk()->assertJsonPath('has_faq', true);

        $slugs = array_column($response->json('docs'), 'slug');
        $this->assertContains('integrations-slack', $slugs);
        $this->assertNotContains('faq', $slugs);
    }

    public function test_a_page_is_rendered_with_links_kept_in_the_reader(): void
    {
        $this->getJson('/support/api/docs/faq')
            ->assertOk()
            ->assertJsonPath('doc.title', 'Frequently asked questions')
            ->assertJsonPath('doc.url', 'http://localhost/help/faq')
            ->assertJson(fn ($json) => $json->where('doc.html', fn ($html) => str_contains($html, 'href="#support-doc=integrations-slack"')
                && ! str_contains($html, '<h1>'))->etc());

        $this->getJson('/support/api/docs/nope')->assertNotFound();
    }

    public function test_raw_html_in_the_docs_is_escaped(): void
    {
        \FifteenPeas\Support\Models\Document::where('slug', 'team')->update(['content' => "# Team\n\n<script>alert(1)</script>\n\nA [link](javascript:alert(1)) here."]);

        $html = $this->getJson('/support/api/docs/team')->json('doc.html');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    public function test_faq_entries_are_the_answered_questions(): void
    {
        $faq = $this->getJson('/support/api/faq')->assertOk()->json('faq');

        $this->assertSame(['Can I connect Slack?', 'Is there an API?'], array_column($faq, 'question'));
        $this->assertStringContainsString('href="#support-doc=integrations-slack"', $faq[0]['html']);
    }

    public function test_search_returns_sections(): void
    {
        $this->getJson('/support/api/docs/search?q=slack')
            ->assertOk()
            ->assertJsonPath('results.0.slug', 'integrations-slack');
    }

    public function test_the_public_help_centre_renders_pages_with_structured_data(): void
    {
        $this->get('/help')->assertOk()
            ->assertSee('Acme help')
            ->assertSee('href="http://localhost/help/faq"', false)
            ->assertSee('<link rel="canonical" href="http://localhost/help">', false);

        $page = $this->get('/help/integrations-slack')->assertOk();
        $page->assertSee('<h1>Slack notifications</h1>', false)
            ->assertSee('"@type":"TechArticle"', false)
            ->assertSee('<link rel="alternate" type="text/markdown" href="http://localhost/help/integrations-slack.md">', false);

        $this->get('/help/faq')->assertOk()
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"name":"Can I connect Slack?"', false)
            ->assertSee('href="http://localhost/help/integrations-slack"', false);

        // The markdown copy still answers next to the page.
        $this->get('/help/integrations-slack.md')->assertOk();
        $this->get('/help/nope')->assertNotFound();
    }

    public function test_the_sitemap_lists_every_help_page(): void
    {
        $locs = array_column(Sitemap::urls(), 'loc');

        $this->assertSame('http://localhost/help', $locs[0]);
        $this->assertContains('http://localhost/help/faq', $locs);
        $this->assertContains('http://localhost/help/integrations-slack', $locs);
    }
}
