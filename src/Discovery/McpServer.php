<?php

namespace Tapp\FilamentMcpTokens\Discovery;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;

/**
 * An MCP server the host registered with Mcp::web() behind Sanctum.
 */
final class McpServer
{
    /**
     * @param  class-string<Server>  $class
     * @param  list<Tool>  $tools  Every tool a token can be given, including tools in a ToolSearch catalog.
     */
    public function __construct(
        public readonly string $class,
        public readonly string $name,
        public readonly string $key,
        public readonly string $uri,
        public readonly array $tools,
    ) {}

    public function url(): string
    {
        return url($this->uri);
    }

    /**
     * A group's label comes from whichever of its tools declares one.
     *
     * @return array<string, string> ability => label
     */
    public function abilities(): array
    {
        $abilities = [];
        $declared = [];

        foreach ($this->tools as $tool) {
            $ability = ToolAbility::for($tool);
            $label = ToolAbility::declaredLabel($tool);

            if ($label !== null && ! isset($declared[$ability])) {
                $abilities[$ability] = $declared[$ability] = $label;
            } else {
                $abilities[$ability] ??= ToolAbility::label($tool);
            }
        }

        return $abilities;
    }
}
