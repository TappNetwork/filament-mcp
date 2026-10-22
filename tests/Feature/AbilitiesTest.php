<?php

use Laravel\Mcp\Facades\Mcp;
use Tapp\FilamentMcpTokens\Discovery\McpServerRegistry;
use Tapp\FilamentMcpTokens\Pages\McpTokens;
use Tapp\FilamentMcpTokens\Tests\Fixtures\GrowingServer;
use Tapp\FilamentMcpTokens\Tests\Fixtures\Tools\RestockItem;

afterEach(fn () => GrowingServer::$added = []);

it('makes one ability per tool, sharing a declared group ability', function () {
    expect(app(McpServerRegistry::class)->abilities())->toBe([
        'list_reports' => 'List Reports',
        'manage_reports' => 'Manage reports',
        'count_stock' => 'Count Stock',
        'restock_item' => 'Restock Item',
    ]);
});

it('offers a tool added to a server as an ability with no config change', function () {
    Mcp::web('/mcp/growing', GrowingServer::class)->middleware('auth:sanctum');
    $registry = app(McpServerRegistry::class);

    expect($registry->find('Growing')->abilities())->toBe(['count_stock' => 'Count Stock']);

    GrowingServer::$added = [RestockItem::class];
    $registry->flush();

    expect($registry->find('Growing')->abilities())->toBe([
        'count_stock' => 'Count Stock',
        'restock_item' => 'Restock Item',
    ]);
});

it('adds extra abilities from config for routes that are not MCP tools', function () {
    config()->set('filament-mcp-tokens.abilities.extra', ['export_csv' => 'Export CSV']);

    $page = new McpTokens;
    $options = (fn () => $this->getAbilityOptions())->call($page);

    expect($options)->toHaveKey('export_csv', 'Export CSV')->toHaveKey('manage_reports');
});
