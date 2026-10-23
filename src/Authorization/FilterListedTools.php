<?php

namespace Tapp\FilamentMcp\Authorization;

use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

/**
 * Wraps tools/list: hides the tools the token can't call.
 */
class FilterListedTools implements Method
{
    public function __construct(
        private readonly Method $inner,
        private readonly ToolAuthorizer $authorizer,
    ) {}

    /**
     * @return iterable<JsonRpcResponse>|JsonRpcResponse
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): iterable|JsonRpcResponse
    {
        if ($this->authorizer->guardedServer($context) === null) {
            return $this->inner->handle($request, $context);
        }

        $visible = $context->tools()
            ->filter(fn (Tool $tool): bool => $this->authorizer->canSee($tool))
            ->values()
            ->all();

        $filtered = clone $context;
        (function (array $tools): void {
            $this->tools = $tools;
        })->call($filtered, $visible);

        return $this->inner->handle($request, $filtered);
    }
}
