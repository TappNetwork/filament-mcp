<?php

namespace Tapp\FilamentMcp\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Tapp\FilamentMcp\Facades\McpAudit;

#[Name('list_reports')]
class ListReports extends Tool
{
    protected string $description = 'List reports.';

    public function handle(Request $request): Response
    {
        McpAudit::with(['result_count' => 2]);

        return Response::text('report-1, report-2');
    }
}
