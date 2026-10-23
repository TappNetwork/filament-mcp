<?php

namespace Tapp\FilamentMcp\Tests\Fixtures;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Tapp\FilamentMcp\Tests\Fixtures\Tools\CountStock;

/** Not behind Sanctum, so the package leaves it alone. */
#[Name('Public Docs')]
class PublicDocsServer extends Server
{
    protected array $tools = [
        CountStock::class,
    ];
}
