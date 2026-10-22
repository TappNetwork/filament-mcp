<?php

namespace Tapp\FilamentMcpTokens\Tests\Fixtures;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\ToolSearch;
use Tapp\FilamentMcpTokens\Tests\Fixtures\Tools\CountStock;
use Tapp\FilamentMcpTokens\Tests\Fixtures\Tools\RestockItem;

/** Uses a ToolSearch catalog, so tools run through execute_tools. */
#[Name('Warehouse')]
class WarehouseServer extends Server
{
    protected array $tools = [
        ToolSearch::class => [CountStock::class, RestockItem::class],
    ];
}
