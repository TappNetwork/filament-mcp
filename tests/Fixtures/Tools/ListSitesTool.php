<?php

namespace Tapp\FilamentMcp\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list_sites')]
class ListSitesTool extends Tool
{
    protected string $description = 'list_sites.';

    public function handle(Request $request): Response
    {
        return Response::text('ok');
    }
}
