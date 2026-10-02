<?php

namespace FifteenPeas\Support\FreeScout;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The one way out to FreeScout. The host comes from config only, and every
 * call is bounded: a helpdesk hiccup must not pin a queue worker.
 */
class FreeScoutClient
{
    public function configured(): bool
    {
        return filled(config('support.freescout.url'))
            && filled(config('support.freescout.api_key'))
            && filled(config('support.freescout.mailbox_id'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return int the new conversation's id
     */
    public function createConversation(array $payload): int
    {
        $response = $this->request()->post('api/conversations', $payload)->throw();

        // FreeScout answers 201 with an empty body and the id in a header.
        $id = $response->header('Resource-ID') ?: $response->json('id');

        if (! is_numeric($id)) {
            throw new RuntimeException('FreeScout created the conversation but returned no Resource-ID.');
        }

        return (int) $id;
    }

    /**
     * @param  array<int, string>  $tags
     */
    public function tagConversation(int $id, array $tags): void
    {
        $this->request()->put("api/conversations/{$id}/tags", ['tags' => $tags])->throw();
    }

    private function request(): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException('FreeScout is not configured: set FREESCOUT_URL, FREESCOUT_API_KEY and FREESCOUT_MAILBOX_ID.');
        }

        return Http::baseUrl(rtrim(config('support.freescout.url'), '/').'/')
            ->withHeaders(['X-FreeScout-API-Key' => config('support.freescout.api_key')])
            ->acceptJson()
            ->connectTimeout(4)
            ->timeout(10);
    }
}
