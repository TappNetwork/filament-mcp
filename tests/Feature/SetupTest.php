<?php

use Laravel\Mcp\Facades\Mcp;
use Tapp\FilamentMcpTokens\Discovery\McpServerRegistry;
use Tapp\FilamentMcpTokens\Pages\McpTokens;
use Tapp\FilamentMcpTokens\Setup\SetupInstructions;
use Tapp\FilamentMcpTokens\Tests\Fixtures\LessonsServer;

it('builds the setup prompt from the server name and route URL', function () {
    $server = app(McpServerRegistry::class)->find('Reports Admin');

    expect(app(SetupInstructions::class)->prompt($server))
        ->toStartWith('Connect the Reports Admin MCP server at https://app.test/mcp/reports. ')
        ->toContain("'Authorization: Bearer <token>'")
        ->toContain("Don't have me paste it into chat.");
});

it('builds the JSON config from the server key and route URL', function () {
    $server = app(McpServerRegistry::class)->find('Reports Admin');

    expect(json_decode(app(SetupInstructions::class)->config($server, 'secret'), true))->toBe([
        'mcpServers' => [
            'reports-admin' => [
                'type' => 'http',
                'url' => 'https://app.test/mcp/reports',
                'headers' => ['Authorization' => 'Bearer secret'],
            ],
        ],
    ]);
});

it('follows the route when the host moves or renames a server', function () {
    Mcp::web('/ai/lessons', LessonsServer::class)->middleware('auth:sanctum');
    app(McpServerRegistry::class)->flush();

    $server = app(McpServerRegistry::class)->find('Lessons');

    expect(app(SetupInstructions::class)->prompt($server))->toStartWith('Connect the Lessons MCP server at https://app.test/ai/lessons. ');
});

it('only builds setup steps while a new token is shown', function () {
    $page = new McpTokens;

    expect($page->getSetupSteps())->toBe([]);

    $page->plainTextToken = '1|secret';

    expect(array_column($page->getSetupSteps(), 'url'))->toBe([
        'https://app.test/mcp/reports',
        'https://app.test/mcp/warehouse',
    ])->and($page->getSetupSteps()[0]['config'])->toContain('Bearer 1|secret');
});
