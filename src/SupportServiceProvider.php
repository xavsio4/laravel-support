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
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

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
        $this->app->singleton('support.anthropic', function () {
            // Without this the SDK sends no key and the API's 401 ("x-api-key
            // header is required") lands on the message row, which reads like
            // a bad key rather than a missing one, usually on the worker.
            if (blank(config('support.ai.api_key'))) {
                throw new RuntimeException(
                    'ANTHROPIC_API_KEY is not set in this process. Answers run on the queue worker: set it in the worker\'s environment too, and redeploy so config:cache picks it up.',
                );
            }

            return new Client(apiKey: config('support.ai.api_key'));
        });
        $this->app->when(AnthropicDocsAnswerer::class)
            ->needs(Client::class)
            ->give(fn ($app) => $app->make('support.anthropic'));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/support.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'support');

        // @supportWidget or @supportWidget(['position' => 'left'])
        Blade::directive('supportWidget', fn ($options) => '<?php echo \\FifteenPeas\\Support\\Widget::tag('.($options ?: '[]').'); ?>');

        RateLimiter::for('support-messages', fn (Request $request) => Limit::perMinute(
            config('support.limits.messages_per_minute'),
        )->by('support:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('support-public', fn (Request $request) => Limit::perMinute(120)->by('support-public:'.$request->ip()));
        RateLimiter::for('support-mcp', fn (Request $request) => Limit::perMinute(
            config('support.mcp.requests_per_minute'),
        )->by('support-mcp:'.$request->ip()));

        if ($this->app->runningInConsole()) {
            $this->commands([IndexDocsCommand::class, EvalCommand::class]);

            $this->publishes([__DIR__.'/../config/support.php' => config_path('support.php')], 'support-config');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/support')], 'support-views');
        }
    }
}
