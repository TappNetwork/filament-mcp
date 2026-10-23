<?php

namespace Tapp\FilamentMcp\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('count_stock')]
class CountStock extends Tool
{
    protected string $description = 'Count stock in the warehouse.';

    public function handle(Request $request): Response
    {
        return Response::text('42');
    }
}
