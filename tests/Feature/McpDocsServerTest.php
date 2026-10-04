<?php

namespace FifteenPeas\Support\Tests\Feature;

use FifteenPeas\Support\Mcp\DocsServer;
use FifteenPeas\Support\Mcp\Tools\GetDoc;
use FifteenPeas\Support\Mcp\Tools\ListDocs;
use FifteenPeas\Support\Mcp\Tools\SearchDocs;
use FifteenPeas\Support\Tests\TestCase;

class McpDocsServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('support:index');
    }

    public function test_search_returns_the_matching_section_with_its_link(): void
    {
        DocsServer::tool(SearchDocs::class, ['query' => 'connect slack channel'])
            ->assertOk()
            ->assertSee(['## Slack notifications', 'slug: integrations-slack', 'url: http://localhost/help/integrations-slack'])
            ->assertDontSee('Getting started');
    }

    public function test_a_question_finds_the_section_whose_heading_answers_it(): void
    {
        $response = DocsServer::tool(SearchDocs::class, ['query' => 'why is my report not in the inbox', 'limit' => 1]);

        $response->assertSee(['## Troubleshooting › A report is not in the inbox', 'Unconfirmed reports stay hidden']);
    }

    public function test_snippets_are_plain_text_and_passing_mentions_are_dropped(): void
    {
        $response = DocsServer::tool(SearchDocs::class, ['query' => 'screenshot firefox']);

        $response->assertSee('In Firefox the screenshot is drawn from the page: no prompt visible part only')
            ->assertDontSee('**')
            // Getting started mentions neither word in a heading: below the floor.
            ->assertDontSee('## Getting started');
    }

    public function test_word_forms_match(): void
    {
        DocsServer::tool(SearchDocs::class, ['query' => 'invite', 'limit' => 1])->assertSee('## Team › Members');
    }

    public function test_search_with_nothing_matching_says_so(): void
    {
        DocsServer::tool(SearchDocs::class, ['query' => 'kubernetes helm chart'])
            ->assertSee('No section matches');
    }

    public function test_search_requires_a_query(): void
    {
        DocsServer::tool(SearchDocs::class, [])->assertHasErrors();
    }

    public function test_get_doc_returns_the_page_and_its_source(): void
    {
        DocsServer::tool(GetDoc::class, ['slug' => 'getting-started'])
            ->assertSee(['# Getting started', 'paste the snippet', 'Source: https://acme.test/docs/getting-started']);

        DocsServer::tool(GetDoc::class, ['slug' => 'missing'])->assertHasErrors(['No page with slug "missing"']);
    }

    public function test_list_docs_lists_every_page(): void
    {
        DocsServer::tool(ListDocs::class)
            ->assertSee(['- getting-started: Getting started — Create a site and install the snippet.', '- integrations-slack: Slack notifications']);
    }

    public function test_the_server_answers_over_http(): void
    {
        $response = $this->postJson('/mcp/docs', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']],
        ], ['Accept' => 'application/json, text/event-stream']);

        $response->assertOk();
        $this->assertSame('Acme documentation', $response->json('result.serverInfo.name'));
        $this->assertStringContainsString('official user documentation for Acme', $response->json('result.instructions'));

        $tools = $this->postJson('/mcp/docs', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], [
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2025-06-18',
            'Mcp-Session-Id' => $response->headers->get('Mcp-Session-Id'),
        ]);

        $this->assertEqualsCanonicalizing(
            ['list_docs', 'search_docs', 'get_doc'],
            array_column($tools->json('result.tools'), 'name'),
        );
        $this->assertTrue($tools->json('result.tools.0.annotations.readOnlyHint'));
    }
}
