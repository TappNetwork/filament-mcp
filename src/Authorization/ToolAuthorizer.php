<?php

namespace Tapp\FilamentMcp\Authorization;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ExecuteTools;
use Laravel\Mcp\Server\Tools\SearchTools;
use Laravel\Mcp\Server\Tools\ToolSearch;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Tapp\FilamentMcp\Discovery\McpServer;
use Tapp\FilamentMcp\Discovery\McpServerRegistry;
use Tapp\FilamentMcp\Discovery\ToolAbility;

/**
 * Decides which tools the current token may call on a discovered server.
 */
class ToolAuthorizer
{
    public function __construct(
        private readonly McpServerRegistry $registry,
    ) {}

    /**
     * The discovered server this context belongs to, when a user is signed
     * in. A local server with no user (stdio, tests) is not guarded.
     */
    public function guardedServer(ServerContext $context): ?McpServer
    {
        if (Auth::user() === null) {
            return null;
        }

        return $this->registry->find($context->implementation->name);
    }

    public function user(): ?Authenticatable
    {
        return Auth::user();
    }

    public function allows(string $ability): bool
    {
        $user = Auth::user();

        if ($user === null || ! method_exists($user, 'tokenCan')) {
            return false;
        }

        if ($this->tokenExpired()) {
            return false;
        }

        return $user->tokenCan($ability);
    }

    /**
     * Whether the current token's expires_at is already past.
     *
     * Passport accepts a bearer until the JWT exp, which uses the system clock.
     * The token row stores that same moment. Refusing a past row here honors the
     * lifetime chosen at issue, including tokens whose row was given an expiry
     * the JWT did not carry.
     */
    public function tokenExpired(): bool
    {
        $user = Auth::user();

        if ($user === null || ! method_exists($user, 'currentAccessToken')) {
            return false;
        }

        $token = $user->currentAccessToken();

        if (! is_object($token)) {
            return false;
        }

        $expiresAt = data_get($token, 'expires_at');

        if (! is_string($expiresAt) && ! $expiresAt instanceof DateTimeInterface) {
            return false;
        }

        return Carbon::parse($expiresAt)->isPast();
    }

    /**
     * Whether the tool should appear in tools/list for the current token.
     */
    public function canSee(Tool $tool): bool
    {
        return $tool instanceof SearchTools
            || $tool instanceof ExecuteTools
            || $this->allows(ToolAbility::for($tool));
    }

    /**
     * The first ability the call needs that the token lacks, or null.
     * For execute_tools every tool in the calls list is checked.
     */
    public function missingAbility(Tool $tool, JsonRpcRequest $request): ?string
    {
        if ($tool instanceof SearchTools) {
            return null;
        }

        if ($tool instanceof ExecuteTools) {
            return $this->missingCatalogAbility($tool, $request);
        }

        $ability = ToolAbility::for($tool);

        return $this->allows($ability) ? null : $ability;
    }

    private function missingCatalogAbility(ExecuteTools $tool, JsonRpcRequest $request): ?string
    {
        /** @var ToolSearch $catalog */
        $catalog = (fn (): ToolSearch => $this->catalog)->call($tool);

        $abilities = [];

        /** @var iterable<Tool> $entries */
        $entries = (fn (): iterable => $this->resolvedTools())->call($catalog);

        foreach ($entries as $entry) {
            $abilities[$entry->name()] = ToolAbility::for($entry);
        }

        foreach ($this->catalogCallNames($request) as $name) {
            if (! isset($abilities[$name])) {
                continue;
            }

            if (! $this->allows($abilities[$name])) {
                return $abilities[$name];
            }
        }

        return null;
    }

    /**
     * The tool names an execute_tools request asks for.
     *
     * @return list<string>
     */
    public function catalogCallNames(JsonRpcRequest $request): array
    {
        $arguments = $request->get('arguments');
        $calls = is_array($arguments) ? ($arguments['calls'] ?? []) : [];
        $names = [];

        foreach (is_array($calls) ? $calls : [] as $call) {
            if (is_array($call) && is_string($call['name'] ?? null)) {
                $names[] = $call['name'];
            }
        }

        return $names;
    }
}
