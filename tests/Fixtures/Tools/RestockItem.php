<?php

namespace Tapp\FilamentMcp\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('restock_item')]
class RestockItem extends Tool
{
    protected string $description = 'Restock an item in the warehouse.';

    public function handle(Request $request): Response
    {
        return Response::text('restocked');
    }
}
