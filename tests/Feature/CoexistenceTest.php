<?php

use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Tapp\FilamentMcpTokens\Discovery\McpServerRegistry;
use Tapp\FilamentMcpTokens\Tests\Fixtures\LessonsServer;

beforeEach(function () {
    Mcp::web('/mcp/lessons', LessonsServer::class)->middleware('auth:sanctum');
    app(McpServerRegistry::class)->flush();
});

it('offers abilities for every server, including ones other packages supply', function () {
    expect(array_keys(app(McpServerRegistry::class)->abilities()))
        ->toBe(['list_reports', 'manage_reports', 'count_stock', 'restock_item', 'list_lessons']);
});

it('keeps a token for one server out of the others', function () {
    $token = $this->makeUser()->createToken('t', ['list_lessons'])->plainTextToken;

    callTool('/mcp/lessons', 'list_lessons', [], $token)->assertJsonPath('result.content.0.text', 'lesson-1');
    callTool('/mcp/reports', 'list_reports', [], $token)->assertJsonPath('result.content.0.text', 'Token lacks ability list_reports.');
});

it('runs alongside a host decorator of tool calls', function () {
    $seen = new ArrayObject;

    app()->extend(CallTool::class, fn (Method $inner) => new class($inner, $seen) implements Method
    {
        public function __construct(private Method $inner, private ArrayObject $seen) {}

        public function handle(JsonRpcRequest $request, ServerContext $context): iterable|JsonRpcResponse
        {
            $this->seen[] = $request->get('name');

            return $this->inner->handle($request, $context);
        }
    });

    $token = $this->makeUser()->createToken('t', ['list_lessons'])->plainTextToken;

    callTool('/mcp/lessons', 'list_lessons', [], $token)->assertJsonPath('result.content.0.text', 'lesson-1');
    callTool('/mcp/reports', 'list_reports', [], $token)->assertJsonPath('result.isError', true);

    expect($seen->getArrayCopy())->toBe(['list_lessons', 'list_reports']);
});

it('leaves the filament-sanctum config to the host', function () {
    $defaults = require __DIR__.'/../../vendor/devtical/filament-sanctum/config/filament-sanctum.php';

    expect(config('filament-sanctum'))->toBe($defaults)
        ->and(config('filament-mcp-tokens.navigation.slug'))->toBe('api-tokens');
});
