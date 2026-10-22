<?php

use Illuminate\Routing\Router;
use Laravel\Mcp\Facades\Mcp;
use Tapp\FilamentMcpTokens\Discovery\McpServerRegistry;
use Tapp\FilamentMcpTokens\Tests\Fixtures\LessonsServer;

it('finds only the servers the host put behind Sanctum', function () {
    $servers = app(McpServerRegistry::class)->servers();

    expect(array_map(fn ($server) => $server->name, $servers))->toBe(['Reports Admin', 'Warehouse'])
        ->and(array_map(fn ($server) => $server->url(), $servers))->toBe([
            'https://app.test/mcp/reports',
            'https://app.test/mcp/warehouse',
        ])
        ->and(array_map(fn ($server) => $server->key, $servers))->toBe(['reports-admin', 'warehouse']);
});

it('lists catalog tools, not the search and execute wrappers', function () {
    $warehouse = app(McpServerRegistry::class)->find('Warehouse');

    expect(array_map(fn ($tool) => $tool->name(), $warehouse->tools))->toBe(['count_stock', 'restock_item']);
});

it('finds servers registered with a guard list or named auth middleware', function () {
    Mcp::web('/mcp/lessons', LessonsServer::class)->middleware(['web', 'auth:sanctum,web']);
    app(McpServerRegistry::class)->flush();

    expect(app(McpServerRegistry::class)->find('Lessons')?->url())->toBe('https://app.test/mcp/lessons');
});

it('ignores routes guarded by something other than Sanctum', function () {
    Mcp::web('/mcp/lessons', LessonsServer::class)->middleware('auth:web');
    app(McpServerRegistry::class)->flush();

    expect(app(McpServerRegistry::class)->find('Lessons'))->toBeNull();
});

it('still finds servers when routes are cached', function () {
    $router = app(Router::class);

    foreach ($router->getRoutes() as $route) {
        $route->prepareForSerialization();
    }

    $cached = unserialize(serialize($router->getRoutes()->compile()));
    $router->setCompiledRoutes($cached);
    app(McpServerRegistry::class)->flush();

    expect(array_map(fn ($server) => $server->name, app(McpServerRegistry::class)->servers()))
        ->toBe(['Reports Admin', 'Warehouse']);
});
