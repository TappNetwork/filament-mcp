<?php

namespace Tapp\FilamentMcp\Tests\Fixtures;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Tapp\FilamentMcp\Tests\Fixtures\Tools\CountStock;

/** Its tool list can grow during a test, like a developer adding a tool. */
#[Name('Growing')]
class GrowingServer extends Server
{
    /** @var list<class-string> */
    public static array $added = [];

    protected array $tools = [
        CountStock::class,
    ];

    protected function boot(): void
    {
        $this->tools = [...$this->tools, ...static::$added];
    }
}
