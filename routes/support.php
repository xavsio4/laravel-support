<?php

use FifteenPeas\Support\FreeScout\WebhookController;
use FifteenPeas\Support\Http\Controllers\ConversationController;
use FifteenPeas\Support\Http\Controllers\MessageController;
use FifteenPeas\Support\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

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
