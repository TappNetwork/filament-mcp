<?php

namespace Tapp\FilamentMcpTokens\Discovery;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteAction;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Middleware\ValidateMcpHeaders;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolSearch;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\SerializableClosure\SerializableClosure;
use Laravel\SerializableClosure\UnsignedSerializableClosure;
use ReflectionFunction;

/**
 * Finds the MCP servers the host registered with Mcp::web() behind the
 * Sanctum guard. Reads the router, so it also works with cached routes.
 */
class McpServerRegistry
{
    /** @var list<McpServer>|null */
    private ?array $servers = null;

    public function __construct(
        private readonly Router $router,
        private readonly Container $container,
    ) {}

    /**
     * @return list<McpServer>
     */
    public function servers(): array
    {
        return $this->servers ??= $this->discover();
    }

    public function find(string $name): ?McpServer
    {
        foreach ($this->servers() as $server) {
            if ($server->name === $name) {
                return $server;
            }
        }

        return null;
    }

    /**
     * Every tool ability across the servers, first label wins.
     *
     * @return array<string, string> ability => label
     */
    public function abilities(): array
    {
        $abilities = [];

        foreach ($this->servers() as $server) {
            $abilities += $server->abilities();
        }

        return $abilities;
    }

    public function flush(): void
    {
        $this->servers = null;
    }

    /**
     * @return list<McpServer>
     */
    protected function discover(): array
    {
        $servers = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if (! in_array('POST', $route->methods(), true)) {
                continue;
            }

            $middleware = $this->router->gatherRouteMiddleware($route);

            if (! in_array(ValidateMcpHeaders::class, $middleware, true) || ! $this->usesSanctum($middleware)) {
                continue;
            }

            $class = $this->serverClass($route);

            if ($class === null) {
                continue;
            }

            $servers[] = $this->describe($class, $route);
        }

        return $servers;
    }

    /**
     * @param  array<int, mixed>  $middleware
     */
    protected function usesSanctum(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            [$name, $parameters] = array_pad(explode(':', $entry, 2), 2, '');

            if (($name === Authenticate::class || $name === 'auth') && in_array('sanctum', explode(',', $parameters), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mcp::web() registers a closure that captures the server class.
     *
     * @return class-string<Server>|null
     */
    protected function serverClass(Route $route): ?string
    {
        $action = $route->getAction();
        $uses = $action['uses'] ?? null;

        if (RouteAction::containsSerializedClosure($action) && is_string($uses)) {
            $closure = unserialize($uses);
            $uses = $closure instanceof SerializableClosure || $closure instanceof UnsignedSerializableClosure
                ? $closure->getClosure()
                : null;
        }

        if (! $uses instanceof Closure) {
            return null;
        }

        $class = (new ReflectionFunction($uses))->getClosureUsedVariables()['serverClass'] ?? null;

        return is_string($class) && is_subclass_of($class, Server::class) ? $class : null;
    }

    /**
     * @param  class-string<Server>  $class
     */
    protected function describe(string $class, Route $route): McpServer
    {
        $server = $this->container->make($class, ['transport' => new FakeTransporter]);
        $server->start();

        $name = $server->createContext()->implementation->name;

        return new McpServer(
            class: $class,
            name: $name,
            key: Str::slug($name) ?: Str::slug(class_basename($class)),
            uri: $route->uri(),
            tools: $this->tools($server),
        );
    }

    /**
     * The tools a token can be given: the server's tools and the tools in a
     * ToolSearch catalog, not the catalog's search_tools and execute_tools.
     *
     * @return list<Tool>
     */
    protected function tools(Server $server): array
    {
        /** @var array<int|string, Tool|string|array<int, Tool|string>> $configured */
        $configured = (fn (): array => $this->tools)->call($server);

        $tools = [];

        foreach ($configured as $key => $entry) {
            foreach ($key === ToolSearch::class && is_array($entry) ? $entry : [$entry] as $tool) {
                if (is_array($tool)) {
                    continue;
                }

                $tools[] = is_string($tool) ? $this->container->make($tool) : $tool;
            }
        }

        return $tools;
    }
}
