<?php

namespace FifteenPeas\Support\Tests;

use FifteenPeas\Support\SupportServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [SupportServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('support.app_name', 'Acme');
        $app['config']->set('support.docs_path', __DIR__.'/fixtures/docs');
        $app['config']->set('support.freescout', [
            'url' => 'https://support.example.test',
            'api_key' => 'fs-key',
            'mailbox_id' => 3,
            'user_id' => 1,
            'webhook_secret' => 'hook-secret',
            'tag' => 'Acme',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function user(string $email = 'ada@example.test'): User
    {
        return User::create(['name' => 'Ada', 'email' => $email, 'password' => 'x']);
    }
}
