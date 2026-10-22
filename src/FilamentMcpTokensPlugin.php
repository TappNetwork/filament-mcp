<?php

namespace Tapp\FilamentMcpTokens;

use Devtical\Sanctum\SanctumPlugin;
use Tapp\FilamentMcpTokens\Pages\McpTokens;

/**
 * Registers the API tokens page. Use it in place of SanctumPlugin.
 */
class FilamentMcpTokensPlugin extends SanctumPlugin
{
    public function getId(): string
    {
        return 'filament-mcp-tokens';
    }

    /**
     * @return class-string<McpTokens>
     */
    protected function getPage(): string
    {
        return McpTokens::class;
    }
}
