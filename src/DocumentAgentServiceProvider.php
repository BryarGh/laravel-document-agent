<?php

namespace DocumentAgent;

use Illuminate\Support\ServiceProvider;

class DocumentAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/document-agent.php', 'document-agent');

        $this->app->singleton(DocumentAgentClient::class, function ($app) {
            $config = $app['config']->get('document-agent', []);
            return new DocumentAgentClient($app['http'], $config);
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'document-agent');

        $this->publishes([
            __DIR__ . '/../config/document-agent.php' => config_path('document-agent.php'),
        ], 'document-agent-config');

        $this->publishes([
            __DIR__ . '/../resources/js/agent.js' => public_path('vendor/document-agent/agent.js'),
        ], 'document-agent-assets');
    }
}
