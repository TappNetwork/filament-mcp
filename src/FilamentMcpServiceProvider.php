<?php

namespace Tapp\FilamentMcp;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\Methods\ListTools;
use Tapp\FilamentMcp\Audit\ToolCallAudit;
use Tapp\FilamentMcp\Authorization\AuthorizeToolCalls;
use Tapp\FilamentMcp\Authorization\FilterListedTools;
use Tapp\FilamentMcp\Authorization\ToolAuthorizer;
use Tapp\FilamentMcp\Commands\InstallCommand;
use Tapp\FilamentMcp\Discovery\McpServerRegistry;

class FilamentMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-mcp.php', 'filament-mcp');

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
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-mcp');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-mcp');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filament-mcp.php' => config_path('filament-mcp.php'),
            ], 'filament-mcp-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/filament-mcp'),
            ], 'filament-mcp-views');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/filament-mcp'),
            ], 'filament-mcp-translations');

            $this->commands([InstallCommand::class]);
        }
    }
}
