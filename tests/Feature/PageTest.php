<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Tapp\FilamentMcp\Discovery\McpServerRegistry;
use Tapp\FilamentMcp\Pages\McpTokens;

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
    config()->set('filament-mcp.abilities.extra', ['export_csv' => 'Export CSV']);
    $this->mock(McpServerRegistry::class, function ($mock) {
        $mock->shouldReceive('servers')->andReturn([]);
        $mock->shouldReceive('abilities')->andReturn([]);
    });

    livewire(McpTokens::class)
        ->callAction('new', ['name' => 'Script', 'expiration' => '30', 'abilities' => ['export_csv']])
        ->assertSee('No MCP server uses these tokens yet')
        ->assertDontSeeHtml('data-setup-step="1"');
});

it('respects the authorization gate', function () {
    config()->set('filament-mcp.authorization.gate', 'manage-api-tokens');
    Gate::define('manage-api-tokens', fn () => false);

    expect(McpTokens::canAccess())->toBeFalse();
});

it('puts the agent-neutral prompt in step 1 and the token only in step 2', function () {
    $page = livewire(McpTokens::class)
        ->callAction('new', ['name' => 'Agent', 'expiration' => '30', 'abilities' => ['list_reports']]);

    $plain = $page->get('plainTextToken');
    $step = collect($page->instance()->getSetupSteps())->firstWhere('key', 'reports-admin');

    expect($plain)->toBeString()->not->toBeEmpty()
        ->and($step['prompt'])->not->toContain($plain)
        ->and(json_decode($step['config'], true)['mcpServers']['reports-admin'])->toBe([
            'type' => 'http',
            'url' => 'https://app.test/mcp/reports',
            'headers' => ['Authorization' => 'Bearer '.$plain],
        ]);

    $page->assertSee('Copy setup prompt')
        ->assertSee('Copy token')
        ->assertSee('Copy JSON config')
        ->assertSeeHtml('class="fmt-note"')
        ->assertSee(__('filament-mcp::setup.sensitive'));
});

it('keeps the new token out of the session', function () {
    livewire(McpTokens::class)
        ->callAction('new', ['name' => 'Agent', 'expiration' => 'never', 'abilities' => ['list_reports']]);

    expect(json_encode(session()->all()))->not->toContain('|');
});

it('does not show the token on a fresh visit', function () {
    livewire(McpTokens::class)
        ->callAction('new', ['name' => 'Agent', 'expiration' => '30', 'abilities' => ['list_reports']]);

    livewire(McpTokens::class)
        ->assertSet('plainTextToken', null)
        ->assertDontSeeHtml('data-setup-step="2"');
});

it('offers one ability per MCP tool plus the configured extras', function () {
    config()->set('filament-mcp.abilities.extra', ['reports:export' => 'Export reports']);

    $options = (fn () => $this->getAbilityOptions())->call(new McpTokens);

    expect(array_keys($options))->toBe(['list_reports', 'manage_reports', 'count_stock', 'restock_item', 'reports:export']);
});

it('sets the expiry from a preset, a custom date or never', function () {
    $this->freezeSecond();

    livewire(McpTokens::class)
        ->callAction('new', ['name' => 'Week', 'expiration' => '7', 'abilities' => ['list_reports']])
        ->callAction('new', ['name' => 'Custom', 'expiration' => 'custom', 'expires_at' => now()->addDays(3)->toDateTimeString(), 'abilities' => ['list_reports']])
        ->callAction('new', ['name' => 'Forever', 'expiration' => 'never', 'abilities' => ['list_reports']]);

    $expiry = PersonalAccessToken::query()->pluck('expires_at', 'name');

    expect($expiry['Week']->equalTo(now()->addDays(7)))->toBeTrue()
        ->and($expiry['Custom']->equalTo(now()->addDays(3)))->toBeTrue()
        ->and($expiry['Forever'])->toBeNull();
});

it('creates tokens that never expire when expiry is turned off', function () {
    config()->set('filament-mcp.abilities.allow_expiration', false);

    livewire(McpTokens::class)
        ->callAction('new', ['name' => 'Forever', 'abilities' => ['list_reports']])
        ->assertHasNoActionErrors();

    expect(PersonalAccessToken::query()->sole()->expires_at)->toBeNull();
});

it('lists only the signed-in user\'s tokens and revokes them', function () {
    $mine = $this->user->createToken('Mine', ['list_reports'])->accessToken;
    $theirs = $this->makeUser()->createToken('Theirs', ['list_reports'])->accessToken;

    livewire(McpTokens::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs])
        ->callTableAction('revoke', $mine);

    expect(PersonalAccessToken::query()->pluck('name')->all())->toBe(['Theirs']);
});

it('summarizes token abilities with their tool labels', function () {
    $this->user->createToken('Some', ['list_reports', 'manage_reports']);
    $this->user->createToken('All', ['*']);

    livewire(McpTokens::class)
        ->assertSee('2 abilities')
        ->assertSee('All tools');
});

it('adds the page to the user menu and hides it from users the gate refuses', function () {
    Filament::bootCurrentPanel();
    $item = fn () => collect(Filament::getPanel('admin')->getUserMenuItems())
        ->first(fn ($action) => $action->getName() === 'mcp-tokens');

    expect($item()?->getUrl())->toBe('https://app.test/admin/api-tokens');

    config()->set('filament-mcp.authorization.gate', 'manage-api-tokens');
    Gate::define('manage-api-tokens', fn () => false);

    expect($item())->toBeNull();
});
