<?php

namespace Tapp\FilamentMcp\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Tapp\FilamentMcp\Attributes\Ability;
use Tapp\FilamentMcp\Facades\McpAudit;

#[Name('rename_report')]
#[Ability('manage_reports')]
class RenameReport extends Tool
{
    protected string $description = 'Rename a report.';

    public function handle(Request $request): Response
    {
        // A tool's own details must not overwrite what the call actually did.
        McpAudit::with(['outcome' => 'renamed', 'tool' => 'something_else', 'report' => 'missing']);

        return Response::error('Report not found.');
    }
}
