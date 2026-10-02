<?php

namespace FifteenPeas\Support\Tests\Feature;

use Anthropic\Client;
use FifteenPeas\Support\Ai\AnthropicDocsAnswerer;
use FifteenPeas\Support\Models\Document;
use FifteenPeas\Support\Tests\TestCase;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * The real SDK against a scripted transport: what goes over the wire, and how
 * char_location citations come back.
 */
class AnthropicDocsAnswererTest extends TestCase
{
    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> */
    private array $sent = [];

    private MockHandler $responses;

    protected function setUp(): void
    {
        parent::setUp();

        $this->responses = new MockHandler;
        $stack = HandlerStack::create($this->responses);
        $stack->push(Middleware::history($this->sent));

        $this->app->instance('support.anthropic', new Client(
            apiKey: 'test-key',
            requestOptions: ['transporter' => new Guzzle(['handler' => $stack]), 'maxRetries' => 0],
        ));
    }

    private function message(array $content, string $stopReason = 'end_turn'): Response
    {
        return new Response(200, ['Content-Type' => 'application/json', 'request-id' => 'req_test'], json_encode([
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => $content, 'stop_reason' => $stopReason, 'stop_sequence' => null,
            'usage' => [
                'input_tokens' => 40, 'output_tokens' => 30,
                'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 2000,
            ],
        ]));
    }

    private function docs(): array
    {
        return [
            Document::create(['slug' => 'sites', 'title' => 'Sites', 'url' => 'https://acme.test/docs/sites', 'content' => 'Create a site from the dashboard.', 'checksum' => 'a', 'tokens' => 8]),
            Document::create(['slug' => 'slack', 'title' => 'Slack', 'url' => null, 'content' => 'Connect Slack in Settings.', 'checksum' => 'b', 'tokens' => 7]),
        ];
    }

    private function citation(int $index, string $title, string $text): array
    {
        return [
            'type' => 'char_location', 'cited_text' => $text, 'document_index' => $index,
            'document_title' => $title, 'start_char_index' => 0, 'end_char_index' => strlen($text), 'file_id' => null,
        ];
    }

    public function test_it_sends_the_corpus_as_cited_text_documents_and_maps_citations(): void
    {
        [$sites, $slack] = $this->docs();

        $this->responses->append($this->message([
            ['type' => 'text', 'text' => 'Create it from the dashboard', 'citations' => [$this->citation(0, 'Sites', 'Create a site')]],
            ['type' => 'text', 'text' => ', then connect Slack', 'citations' => [
                $this->citation(1, 'Slack', 'Connect Slack'),
                $this->citation(1, 'Slack', 'in Settings'),
            ]],
            ['type' => 'text', 'text' => '.'],
        ]));

        $answer = app(AnthropicDocsAnswerer::class)->answer(
            [$sites, $slack],
            [['role' => 'user', 'content' => 'Hi'], ['role' => 'assistant', 'content' => 'Hello']],
            'How do I start?',
        );

        $this->assertSame('Create it from the dashboard[1], then connect Slack[2].', $answer->text);
        $this->assertSame([
            ['n' => 1, 'slug' => 'sites', 'title' => 'Sites', 'url' => 'https://acme.test/docs/sites', 'cited_text' => 'Create a site'],
            ['n' => 2, 'slug' => 'slack', 'title' => 'Slack', 'url' => null, 'cited_text' => 'Connect Slack'],
        ], $answer->citations);
        $this->assertSame(2000, $answer->usage['cache_read_input_tokens']);

        $request = $this->sent[0]['request'];
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $request->getHeaderLine('anthropic-beta'));

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertSame(['effort' => 'low'], $body['output_config']);
        $this->assertStringContainsString('support assistant for Acme', $body['system']);

        // Documents lead the first (history) user turn; the question is last.
        $first = $body['messages'][0]['content'];
        $this->assertSame(['type' => 'text', 'media_type' => 'text/plain', 'data' => 'Create a site from the dashboard.'], $first[0]['source']);
        $this->assertSame(['enabled' => true], $first[0]['citations']);
        $this->assertArrayNotHasKey('cache_control', $first[0]);
        $this->assertSame(['type' => 'ephemeral'], $first[1]['cache_control']);
        $this->assertSame('Hi', $first[2]['text']);
        $this->assertCount(3, $body['messages']);
        $this->assertSame('How do I start?', $body['messages'][2]['content'][0]['text']);
    }

    public function test_a_refusal_comes_back_refused_and_empty(): void
    {
        $this->responses->append($this->message([], 'refusal'));

        $answer = app(AnthropicDocsAnswerer::class)->answer($this->docs(), [], 'Something');

        $this->assertTrue($answer->refused);
        $this->assertSame([], $answer->citations);
    }
}
