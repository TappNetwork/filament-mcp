<?php

namespace Tapp\FilamentMcpTokens\Pages;

use Devtical\Sanctum\Pages\Sanctum;
use Tapp\FilamentMcpTokens\Discovery\McpServerRegistry;
use Tapp\FilamentMcpTokens\Setup\SetupInstructions;

class McpTokens extends Sanctum
{
    protected string $view = 'filament-mcp-tokens::pages.mcp-tokens';

    public static function getConfigKey(): string
    {
        return 'filament-mcp-tokens';
    }

    public function getTitle(): string
    {
        return __('filament-mcp-tokens::setup.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-mcp-tokens::setup.title');
    }

    /**
     * @return array<string, string>
     */
    protected function getAbilityOptions(): array
    {
        return app(McpServerRegistry::class)->abilities() + (array) static::setting('abilities.extra', []);
    }

    /**
     * The setup steps for each server, built only while the new token is shown.
     *
     * @return list<array{key: string, name: string, url: string, prompt: string, config: string}>
     */
    public function getSetupSteps(): array
    {
        if (blank($this->plainTextToken)) {
            return [];
        }

        $instructions = app(SetupInstructions::class);

        return array_map(fn ($server): array => [
            'key' => $server->key,
            'name' => $server->name,
            'url' => $server->url(),
            'prompt' => $instructions->prompt($server),
            'config' => $instructions->config($server, $this->plainTextToken),
        ], app(McpServerRegistry::class)->servers());
    }
}
