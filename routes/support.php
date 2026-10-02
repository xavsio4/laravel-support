<?php

use FifteenPeas\Support\FreeScout\WebhookController;
use FifteenPeas\Support\Http\Controllers\ConversationController;
use FifteenPeas\Support\Http\Controllers\MessageController;
use FifteenPeas\Support\Http\Controllers\WidgetController;
use FifteenPeas\Support\Mcp\DocsServer;
use FifteenPeas\Support\Public\PublicDocsController;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::prefix(config('support.routes.prefix'))
    ->name('support.')
    ->group(function () {
        Route::middleware(config('support.routes.middleware'))->group(function () {
            Route::get('conversation', [ConversationController::class, 'current'])->name('conversation.current');
            Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
            Route::post('conversations/{conversation}/escalate', [ConversationController::class, 'escalate'])->name('conversations.escalate');
            Route::post('conversations/{conversation}/close', [ConversationController::class, 'close'])->name('conversations.close');
            Route::post('messages', [MessageController::class, 'store'])
                ->middleware('throttle:support-messages')
                ->name('messages.store');
        });

        // Public: the script is the same for everyone and holds no data.
        Route::get('widget.js', WidgetController::class)->name('widget');

        // FreeScout calls this; it is authenticated by signature, not session,
        // so it sits outside the app's middleware (and its CSRF check).
        Route::post('webhooks/freescout', WebhookController::class)->name('webhooks.freescout');
    });

if (config('support.public.enabled')) {
    Route::middleware('throttle:support-public')->name('support.public.')->group(function () {
        Route::get(trim(config('support.public.prefix'), '/').'/{slug}.md', [PublicDocsController::class, 'doc'])
            ->where('slug', '[A-Za-z0-9_-]+')
            ->name('doc');

        if (config('support.public.llms_txt')) {
            Route::get('llms.txt', [PublicDocsController::class, 'index'])->name('llms');
            Route::get('llms-full.txt', [PublicDocsController::class, 'full'])->name('llms-full');
        }
    });
}

if (config('support.mcp.enabled')) {
    Mcp::web(config('support.mcp.path'), DocsServer::class)
        ->middleware('throttle:support-mcp')
        ->name('support.mcp');
}
