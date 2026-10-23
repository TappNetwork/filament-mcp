<?php

namespace Tapp\FilamentMcp;

use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Tapp\FilamentMcp\Pages\McpTokens;

/**
 * Adds the MCP tokens page to a panel, and to its user menu when enabled.
 */
class FilamentMcpPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-mcp';
    }

    /**
     * Override to swap in your own subclass of the page.
     *
     * @return class-string<McpTokens>
     */
    protected function getPage(): string
    {
        return McpTokens::class;
    }

    public function register(Panel $panel): void
    {
        $panel->pages([$this->getPage()]);
    }

    public function boot(Panel $panel): void
    {
        $page = $this->getPage();

        if (! $page::setting('navigation.user_menu.enabled', true)) {
            return;
        }

        $panel->userMenuItems([
            Action::make('mcp-tokens')
                ->label(fn (): string => $page::getNavigationLabel())
                ->icon(fn (): string => $page::getNavigationIcon())
                ->url(fn (): string => $page::getUrl(panel: $panel->getId()))
                ->visible(fn (): bool => $page::canAccess()),
        ]);
    }
}
