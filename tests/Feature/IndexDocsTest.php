<?php

namespace FifteenPeas\Support\Tests\Feature;

use FifteenPeas\Support\Models\Document;
use FifteenPeas\Support\Tests\TestCase;

class IndexDocsTest extends TestCase
{
    public function test_it_syncs_the_folder_and_removes_deleted_docs(): void
    {
        Document::create(['slug' => 'gone', 'title' => 'Gone', 'content' => 'x', 'checksum' => 'x', 'tokens' => 1]);

        $this->artisan('support:index')
            ->expectsOutputToContain('3 documents (3 new, 0 updated, 1 removed)')
            ->assertSuccessful();

        $this->assertSame(['getting-started', 'integrations-slack', 'troubleshooting'], Document::orderBy('slug')->pluck('slug')->all());

        $this->artisan('support:index')
            ->expectsOutputToContain('(0 new, 0 updated, 0 removed)')
            ->assertSuccessful();
    }

    public function test_it_warns_over_the_corpus_budget(): void
    {
        config(['support.max_corpus_tokens' => 5]);

        $this->artisan('support:index')
            ->expectsOutputToContain('time for a retriever')
            ->assertSuccessful();
    }
}
