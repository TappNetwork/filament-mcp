<?php

namespace Tapp\FilamentMcpTokens\Tests\Fixtures;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Tapp\FilamentMcpTokens\Tests\Fixtures\Tools\DeleteReport;
use Tapp\FilamentMcpTokens\Tests\Fixtures\Tools\ListReports;
use Tapp\FilamentMcpTokens\Tests\Fixtures\Tools\RenameReport;

#[Name('Reports Admin')]
class ReportsServer extends Server
{
    protected array $tools = [
        ListReports::class,
        DeleteReport::class,
        RenameReport::class,
    ];
}
