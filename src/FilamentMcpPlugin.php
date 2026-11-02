<?php

namespace Tapp\FilamentMcp;

use Closure;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Tapp\FilamentMcp\Pages\McpTokens;

/**
 * Adds the MCP tokens page to a panel, and to its user menu when enabled.
 */
class FilamentMcpPlugin implements Plugin
{
    use EvaluatesClosures;

    /**
     * When set, decides who can open the token page. When null, the page falls
     * back to authorization.gate in config.
     *
     * @var bool|Closure(Authenticatable|null): bool|null
     */
    protected bool|Closure|null $canAccess = null;

    /**
     * Whether the signed-in user may manage every user's tokens (view, edit
     * abilities, revoke). Default false keeps own-tokens-only.
     *
     * @var bool|Closure(Authenticatable|null): bool
     */
    protected bool|Closure $canManageAllTokens = false;

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

    /**
     * @param  bool|Closure(Authenticatable|null): bool  $condition
     */
    public function canAccess(bool|Closure $condition): static
    {
        $this->canAccess = $condition;

        return $this;
    }

    public function hasAccessCondition(): bool
    {
        return $this->canAccess !== null;
    }

    public function userCanAccess(): bool
    {
        return (bool) $this->evaluate($this->canAccess ?? false, [
            'user' => Auth::user(),
        ]);
    }

    /**
     * @param  bool|Closure(Authenticatable|null): bool  $condition
     */
    public function canManageAllTokens(bool|Closure $condition): static
    {
        $this->canManageAllTokens = $condition;

        return $this;
    }

    public function userCanManageAllTokens(): bool
    {
        return (bool) $this->evaluate($this->canManageAllTokens, [
            'user' => Auth::user(),
        ]);
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
