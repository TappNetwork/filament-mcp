<?php

namespace Tapp\FilamentMcpTokens\Tests\Fixtures;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Tapp\FilamentMcpTokens\Tests\Fixtures\Tools\ListLessons;

/** Stands in for a server another package (such as filament-lms) supplies. */
#[Name('Lessons')]
class LessonsServer extends Server
{
    protected array $tools = [
        ListLessons::class,
    ];
}
