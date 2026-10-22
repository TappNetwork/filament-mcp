<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Tapp\FilamentMcpTokens\Discovery\McpServerRegistry;
use Tapp\FilamentMcpTokens\Pages\McpTokens;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->user = $this->makeUser());
});

it('registers the page in the panel from the package config', function () {
    expect(Filament::getPanel('admin')->getPages())->toContain(McpTokens::class)
        ->and(McpTokens::getSlug())->toBe('api-tokens');
});

it('creates a token with the chosen abilities and shows the setup steps', function () {
    livewire(McpTokens::class)
        ->callAction('new', ['name' => 'Claude', 'expiration' => '30', 'abilities' => ['list_reports', 'manage_reports']])
        ->assertHasNoActionErrors()
        ->assertSeeHtml('data-mcp-server="reports-admin"')
        ->assertSeeHtml('data-mcp-server="warehouse"')
        ->assertSeeHtml('data-setup-step="1"')
        ->assertSeeHtml('data-setup-step="2"')
        ->assertSee('Connect the Reports Admin MCP server at https://app.test/mcp/reports.')
        ->assertSee('Copy JSON config');

    $token = PersonalAccessToken::query()->sole();

    expect($token->abilities)->toBe(['list_reports', 'manage_reports'])
        ->and($token->tokenable->is($this->user))->toBeTrue();
});

it('hides the token again', function () {
    livewire(McpTokens::class)
        ->callAction('new', ['name' => 'Claude', 'expiration' => '30', 'abilities' => ['list_reports']])
        ->call('forgetPlainTextToken')
        ->assertDontSeeHtml('data-setup-step="1"');
});

it('falls back to the bare token when no MCP server uses Sanctum', function () {
    config()->set('filament-mcp-tokens.abilities.extra', ['export_csv' => 'Export CSV']);
    $this->mock(McpServerRegistry::class, function ($mock) {
        $mock->shouldReceive('servers')->andReturn([]);
        $mock->shouldReceive('abilities')->andReturn([]);
    });

    livewire(McpTokens::class)
        ->callAction('new', ['name' => 'Script', 'expiration' => '30', 'abilities' => ['export_csv']])
        ->assertSee('No MCP server uses Sanctum tokens yet')
        ->assertDontSeeHtml('data-setup-step="1"');
});

it('respects the authorization gate', function () {
    config()->set('filament-mcp-tokens.authorization.gate', 'manage-api-tokens');
    Gate::define('manage-api-tokens', fn () => false);

    expect(McpTokens::canAccess())->toBeFalse();
});
