<?php

namespace Tapp\FilamentMcp\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('titled')]
#[Title('Run The Tool')]
class TitledTool extends Tool
{
    protected string $description = 'titled.';

    public function handle(Request $request): Response
    {
        return Response::text('ok');
    }
}
