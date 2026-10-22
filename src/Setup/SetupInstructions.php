<?php

namespace Tapp\FilamentMcpTokens\Setup;

use Tapp\FilamentMcpTokens\Discovery\McpServer;

/**
 * The agent-neutral setup steps shown after a token is created.
 */
class SetupInstructions
{
    /**
     * Step 1. Never contains the token.
     */
    public function prompt(McpServer $server): string
    {
        return __('filament-mcp-tokens::setup.prompt', [
            'name' => $server->name,
            'url' => $server->url(),
        ]);
    }

    /**
     * Step 2. A standard MCP client config that contains the token.
     */
    public function config(McpServer $server, string $token): string
    {
        return json_encode([
            'mcpServers' => [
                $server->key => [
                    'type' => 'http',
                    'url' => $server->url(),
                    'headers' => [
                        'Authorization' => 'Bearer '.$token,
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
