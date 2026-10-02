<?php

return [

    /*
    | The product the assistant supports. It is named in the system prompt and
    | in the "I can only help with ..." reply, so use the name users know.
    */
    'app_name' => env('SUPPORT_APP_NAME', env('APP_NAME', 'this app')),

    /*
    | Markdown files that make up the knowledge base, relative to base_path().
    | Each file may start with YAML front-matter: title, slug, url.
    */
    'docs_path' => env('SUPPORT_DOCS_PATH', 'docs/support'),

    /*
    | The page whose "##" headings are the FAQ's questions. Shown in the
    | widget's FAQ tab and at /help/faq, and indexed like any other page.
    */
    'faq_slug' => env('SUPPORT_FAQ_SLUG', 'faq'),

    /*
    | The whole corpus is sent with every question, so it has a ceiling.
    | support:index warns above it; that is the signal to add a retriever.
    */
    'max_corpus_tokens' => (int) env('SUPPORT_MAX_CORPUS_TOKENS', 150000),

    'ai' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('SUPPORT_AI_MODEL', 'claude-opus-5-5'),
        'effort' => env('SUPPORT_AI_EFFORT', 'low'),
        'max_tokens' => (int) env('SUPPORT_AI_MAX_TOKENS', 4000),
        // Earlier turns sent back with each question, user + assistant.
        'history_turns' => (int) env('SUPPORT_AI_HISTORY_TURNS', 10),
    ],

    'limits' => [
        'message_max_chars' => 2000,
        'messages_per_minute' => (int) env('SUPPORT_MESSAGES_PER_MINUTE', 6),
        'messages_per_conversation' => (int) env('SUPPORT_MESSAGES_PER_CONVERSATION', 40),
        // A pending answer older than this is reported to the widget as failed:
        // the worker is down or the job died, and silence would hide that.
        'pending_timeout_seconds' => 120,
    ],

    'freescout' => [
        // The host is pinned here and nowhere else: it is the only place the
        // escalation call can go.
        'url' => env('FREESCOUT_URL'),
        'api_key' => env('FREESCOUT_API_KEY'),
        'mailbox_id' => env('FREESCOUT_MAILBOX_ID') !== null ? (int) env('FREESCOUT_MAILBOX_ID') : null,
        // The agent the transcript note is filed as; notes need one. Without
        // it the transcript is appended to the customer's first message.
        'user_id' => env('FREESCOUT_USER_ID') !== null ? (int) env('FREESCOUT_USER_ID') : null,
        'webhook_secret' => env('FREESCOUT_WEBHOOK_SECRET'),
        'tag' => env('FREESCOUT_TAG', env('SUPPORT_APP_NAME', env('APP_NAME'))),
    ],

    'routes' => [
        'prefix' => env('SUPPORT_ROUTE_PREFIX', 'support/api'),
        // ['web', 'auth'] for a session app, ['api', 'auth:sanctum'] for an SPA.
        'middleware' => ['web', 'auth'],
    ],

    /*
    | Turns the authenticated user into ['id' => ..., 'name' => ..., 'email' => ...].
    | Any invokable class; the default reads id, name and email off the model.
    */
    /*
    | The docs, published for people and for other AI tools: raw markdown per
    | page at /{prefix}/{slug}.md, llms.txt and llms-full.txt at the site root
    | (llmstxt.org), and a read-only MCP server. Everything here is public.
    */
    'public' => [
        'enabled' => env('SUPPORT_PUBLIC_DOCS', true),
        'prefix' => env('SUPPORT_PUBLIC_PREFIX', 'help'),
        // One or two sentences: the blockquote under the title in llms.txt.
        'summary' => env('SUPPORT_PUBLIC_SUMMARY'),
        'llms_txt' => env('SUPPORT_LLMS_TXT', true),
    ],

    'mcp' => [
        'enabled' => env('SUPPORT_MCP', true),
        'path' => env('SUPPORT_MCP_PATH', 'mcp/docs'),
        'requests_per_minute' => 60,
    ],

    'user_resolver' => FifteenPeas\Support\DefaultUserResolver::class,

    'queue' => env('SUPPORT_QUEUE'),

];
