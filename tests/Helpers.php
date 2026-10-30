<?php

use Illuminate\Testing\TestResponse;

function skipWithoutSanctum(): void
{
    if (! trait_exists('Laravel\\Sanctum\\HasApiTokens')) {
        test()->markTestSkipped('laravel/sanctum is not installed.');
    }
}

/**
 * @param  array<string, mixed>  $params
 */
function mcp(string $uri, string $method, array $params = [], ?string $token = null): TestResponse
{
    $headers = ['Accept' => 'application/json, text/event-stream'];

    if ($token !== null) {
        $headers['Authorization'] = "Bearer {$token}";
    }

    $response = test()->postJson($uri, [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => $method,
        'params' => (object) $params,
    ], $headers);

    // Some calls answer as a server-sent event stream; read its last message.
    if (str_starts_with((string) $response->headers->get('Content-Type'), 'text/event-stream')) {
        preg_match_all('/^data: (.+)$/m', $response->streamedContent(), $events);

        return TestResponse::fromBaseResponse(response()->json(json_decode((string) end($events[1]), true), $response->getStatusCode()));
    }

    return $response;
}

/**
 * @param  array<string, mixed>  $arguments
 */
function callTool(string $uri, string $name, array $arguments = [], ?string $token = null): TestResponse
{
    return mcp($uri, 'tools/call', ['name' => $name, 'arguments' => (object) $arguments], $token);
}
