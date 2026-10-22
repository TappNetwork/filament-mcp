<?php

namespace Tapp\FilamentMcpTokens;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\Methods\ListTools;
use Tapp\FilamentMcpTokens\Audit\ToolCallAudit;
use Tapp\FilamentMcpTokens\Authorization\AuthorizeToolCalls;
use Tapp\FilamentMcpTokens\Authorization\FilterListedTools;
use Tapp\FilamentMcpTokens\Authorization\ToolAuthorizer;
use Tapp\FilamentMcpTokens\Commands\InstallCommand;
use Tapp\FilamentMcpTokens\Discovery\McpServerRegistry;

class FilamentMcpTokensServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-mcp-tokens.php', 'filament-mcp-tokens');

        $this->app->scoped(McpServerRegistry::class);
        $this->app->scoped(ToolCallAudit::class);
        $this->app->scoped(ToolAuthorizer::class);

        // Decorate rather than replace, so a host binding of either method still runs.
        $this->app->extend(CallTool::class, fn (Method $method, Application $app): Method => $method instanceof AuthorizeToolCalls
            ? $method
            : new AuthorizeToolCalls($method, $app->make(ToolAuthorizer::class), $app->make(ToolCallAudit::class)));

        $this->app->extend(ListTools::class, fn (Method $method, Application $app): Method => $method instanceof FilterListedTools
            ? $method
            : new FilterListedTools($method, $app->make(ToolAuthorizer::class)));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-mcp-tokens');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-mcp-tokens');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filament-mcp-tokens.php' => config_path('filament-mcp-tokens.php'),
            ], 'filament-mcp-tokens-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/filament-mcp-tokens'),
            ], 'filament-mcp-tokens-views');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/filament-mcp-tokens'),
            ], 'filament-mcp-tokens-translations');

            $this->commands([InstallCommand::class]);
        }
    }
}
