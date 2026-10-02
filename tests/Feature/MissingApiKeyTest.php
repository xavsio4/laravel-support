<?php

namespace FifteenPeas\Support\Tests\Feature;

use FifteenPeas\Support\Jobs\AnswerMessage;
use FifteenPeas\Support\Models\Message;
use FifteenPeas\Support\Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

class MissingApiKeyTest extends TestCase
{
    public function test_a_missing_key_fails_the_answer_with_a_message_that_says_so(): void
    {
        config(['support.ai.api_key' => null]);
        Queue::fake();
        $this->artisan('support:index');

        $this->actingAs($this->user())->postJson('/support/api/messages', ['content' => 'Hi'])->assertCreated();
        $reply = Message::where('role', 'assistant')->first();

        // As a worker does: the job throws on its last attempt, then failed() runs.
        $job = new AnswerMessage($reply);

        try {
            app()->call([$job, 'handle']);
            $this->fail('The job should not run without a key.');
        } catch (RuntimeException $e) {
            $job->failed($e);
        }

        $this->assertSame('failed', $reply->fresh()->status);
        $this->assertStringContainsString('ANTHROPIC_API_KEY is not set in this process', $reply->fresh()->error);
    }
}
