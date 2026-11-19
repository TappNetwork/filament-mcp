<?php

namespace Tapp\FilamentMcp\Tests\Fixtures;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Tapp\FilamentMcp\Tests\Fixtures\Tools\CreateCourse;
use Tapp\FilamentMcp\Tests\Fixtures\Tools\CreateLesson;
use Tapp\FilamentMcp\Tests\Fixtures\Tools\CreateWebhookEvent;
use Tapp\FilamentMcp\Tests\Fixtures\Tools\DeleteWebhookEvent;
use Tapp\FilamentMcp\Tests\Fixtures\Tools\ListLessons;

/** One server whose tools declare different groups; ListLessons declares none. */
#[Name('Grouped')]
class GroupedServer extends Server
{
    protected array $tools = [
        CreateCourse::class,
        CreateLesson::class,
        CreateWebhookEvent::class,
        DeleteWebhookEvent::class,
        ListLessons::class,
    ];
}
