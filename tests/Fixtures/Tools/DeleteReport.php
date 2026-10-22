<?php

namespace Tapp\FilamentMcpTokens\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Tapp\FilamentMcpTokens\Attributes\Ability;

#[Name('delete_report')]
#[Ability('manage_reports', label: 'Manage reports')]
class DeleteReport extends Tool
{
    protected string $description = 'Delete a report.';

    public function handle(Request $request): Response
    {
        return Response::text('deleted');
    }
}
