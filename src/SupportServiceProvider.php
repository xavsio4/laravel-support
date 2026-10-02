<?php

namespace FifteenPeas\Support;

use Anthropic\Client;
use FifteenPeas\Support\Ai\AnthropicDocsAnswerer;
use FifteenPeas\Support\Ai\DocsAnswerer;
use FifteenPeas\Support\Ai\FullCorpusRetriever;
use FifteenPeas\Support\Ai\Retriever;
use FifteenPeas\Support\Console\EvalCommand;
use FifteenPeas\Support\Console\IndexDocsCommand;
use FifteenPeas\Support\Docs\DocsRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/support.php', 'support');

        $this->app->bind(DocsRepository::class, function () {
            $path = config('support.docs_path');

            return new DocsRepository(str_starts_with($path, '/') ? $path : base_path($path));
        });
        $this->app->bindIf(Retriever::class, FullCorpusRetriever::class);
        $this->app->bindIf(DocsAnswerer::class, AnthropicDocsAnswerer::class);

        // Contextual, so an app that already binds the client for its own use
        // keeps its binding and its key.
        $this->app->singleton('support.anthropic', fn () => new Client(apiKey: config('support.ai.api_key')));
        $this->app->when(AnthropicDocsAnswerer::class)
            ->needs(Client::class)
            ->give(fn ($app) => $app->make('support.anthropic'));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/support.php');

        RateLimiter::for('support-messages', fn (Request $request) => Limit::perMinute(
            config('support.limits.messages_per_minute'),
        )->by('support:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        if ($this->app->runningInConsole()) {
            $this->commands([IndexDocsCommand::class, EvalCommand::class]);

            $this->publishes([__DIR__.'/../config/support.php' => config_path('support.php')], 'support-config');
            $this->publishes([__DIR__.'/../dist' => public_path('vendor/support')], 'support-assets');
        }
    }
}
