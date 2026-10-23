<?php

namespace Tapp\FilamentMcp\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list_lessons')]
class ListLessons extends Tool
{
    protected string $description = 'List lessons.';

    public function handle(Request $request): Response
    {
        return Response::text('lesson-1');
    }
}
