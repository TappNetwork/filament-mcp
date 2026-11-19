<?php

namespace Tapp\FilamentMcp\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Tapp\FilamentMcp\Attributes\AbilityGroup;

#[Name('delete_webhook_event')]
#[AbilityGroup('Webhooks')]
class DeleteWebhookEvent extends Tool
{
    protected string $description = 'delete_webhook_event.';

    public function handle(Request $request): Response
    {
        return Response::text('ok');
    }
}
