<?php

namespace Tapp\FilamentMcpTokens\Tests\Fixtures;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Tapp\FilamentMcpTokens\Tests\Fixtures\Tools\CountStock;

/** Not behind Sanctum, so the package leaves it alone. */
#[Name('Public Docs')]
class PublicDocsServer extends Server
{
    protected array $tools = [
        CountStock::class,
    ];
}
