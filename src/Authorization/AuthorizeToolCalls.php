<?php

namespace Tapp\FilamentMcp\Authorization;

use Generator;
use Laravel\Mcp\Server\Contracts\Errable;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tools\ExecuteTools;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Tapp\FilamentMcp\Audit\ToolCallAudit;
use Throwable;

/**
 * Wraps tools/call: refuses a tool the token has no ability for and records
 * the call in the audit log.
 */
class AuthorizeToolCalls implements Errable, Method
{
    public function __construct(
        private readonly Method $inner,
        private readonly ToolAuthorizer $authorizer,
        private readonly ToolCallAudit $audit,
    ) {}

    /**
     * @return iterable<JsonRpcResponse>|JsonRpcResponse
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): iterable|JsonRpcResponse
    {
        $name = $request->get('name');
        $server = is_string($name) ? $this->authorizer->guardedServer($context) : null;
        $tool = $server === null ? null : $context->tools()->first(fn ($tool): bool => $tool->name() === $name);

        if ($server === null || $tool === null) {
            return $this->inner->handle($request, $context);
        }

        $this->audit->start();

        if ($tool instanceof ExecuteTools) {
            $this->audit->with(['calls' => $this->authorizer->catalogCallNames($request)]);
        }

        if ($this->authorizer->tokenExpired()) {
            $this->audit->record($server->name, (string) $name, 'refused');

            return JsonRpcResponse::result($request->id, [
                'content' => [['type' => 'text', 'text' => 'Token has expired.']],
                'isError' => true,
            ]);
        }

        $missing = $this->authorizer->missingAbility($tool, $request);

        if ($missing !== null) {
            $this->audit->record($server->name, (string) $name, 'refused', ['ability' => $missing]);

            return JsonRpcResponse::result($request->id, [
                'content' => [['type' => 'text', 'text' => "Token lacks ability {$missing}."]],
                'isError' => true,
            ]);
        }

        try {
            $response = $this->inner->handle($request, $context);
        } catch (Throwable $exception) {
            $this->audit->record($server->name, (string) $name, 'error');

            throw $exception;
        }

        $this->audit->record($server->name, (string) $name, $this->outcome($response));

        return $response;
    }

    /**
     * @param  iterable<JsonRpcResponse>|JsonRpcResponse  $response
     */
    private function outcome(iterable|JsonRpcResponse $response): string
    {
        if ($response instanceof Generator || ! $response instanceof JsonRpcResponse) {
            return 'streamed';
        }

        $payload = $response->toArray();

        return ($payload['result']['isError'] ?? false) === true || isset($payload['error']) ? 'error' : 'ok';
    }
}
