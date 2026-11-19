<?php

namespace Tapp\FilamentMcp\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Tapp\FilamentMcp\Attributes\AbilityGroup;

#[Name('create_course')]
#[AbilityGroup('LMS')]
class CreateCourse extends Tool
{
    protected string $description = 'create_course.';

    public function handle(Request $request): Response
    {
        return Response::text('ok');
    }
}
