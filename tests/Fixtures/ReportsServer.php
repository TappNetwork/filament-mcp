<?php

namespace Tapp\FilamentMcp\Tests\Fixtures;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Tapp\FilamentMcp\Tests\Fixtures\Tools\DeleteReport;
use Tapp\FilamentMcp\Tests\Fixtures\Tools\ListReports;
use Tapp\FilamentMcp\Tests\Fixtures\Tools\RenameReport;

#[Name('Reports Admin')]
class ReportsServer extends Server
{
    protected array $tools = [
        ListReports::class,
        DeleteReport::class,
        RenameReport::class,
    ];
}
