<?php

use Filament\Facades\Filament;
use Illuminate\Testing\TestResponse;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Tapp\FilamentMcp\Discovery\McpServerRegistry;
use Tapp\FilamentMcp\Pages\McpTokens;
use Tapp\FilamentMcp\Tests\Fixtures\PassportUser;
use Tapp\FilamentMcp\Tests\Fixtures\ReportsServer;
use Tapp\FilamentMcp\Tokens\IssuesMcpTokens;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $directory = sys_get_temp_dir().'/filament-mcp-passport-keys';

    if (! is_dir($directory)) {
        mkdir($directory, 0700, true);
    }

    if (! is_file($directory.'/oauth-private.key')) {
        Passport::loadKeysFrom($directory);
        $this->artisan('passport:keys', ['--force' => true, '--length' => 2048])->assertSuccessful();
    }

    Passport::$validateKeyPermissions = false;
    Passport::$personalAccessTokensExpireIn = null;

    config()->set('passport.private_key', file_get_contents($directory.'/oauth-private.key'));
    config()->set('passport.public_key', file_get_contents($directory.'/oauth-public.key'));
    config()->set('auth.guards.api', [
        'driver' => 'passport',
        'provider' => 'users',
    ]);
    config()->set('auth.providers.users.model', PassportUser::class);
    config()->set('filament-mcp.auth.driver', 'passport');

    $this->artisan('migrate', [
        '--path' => realpath(__DIR__.'/../../vendor/laravel/passport/database/migrations'),
        '--realpath' => true,
    ])->assertSuccessful();

    app(ClientRepository::class)->createPersonalAccessGrantClient('MCP tests', 'users');

    Mcp::web('/mcp/passport', ReportsServer::class)->middleware('auth:api');
    app(McpServerRegistry::class)->flush();

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = PassportUser::query()->create([
        'name' => 'Ada',
        'email' => 'ada'.uniqid().'@example.com',
    ]);
    $this->actingAs($this->user);
});

afterEach(function () {
    Passport::$personalAccessTokensExpireIn = null;
});

it('issues, lists, calls, revokes, and expires a scoped passport token', function () {
    $plain = app(IssuesMcpTokens::class)->issue(
        $this->user,
        'Claude',
        ['list_reports'],
        now()->addDays(7),
    );

    $token = Token::query()->where('name', 'Claude')->sole();

    expect(jwtExpiry($plain))->toBeBetween(now()->addDays(7)->getTimestamp() - 5, now()->addDays(7)->getTimestamp() + 5)
        ->and(abs($token->expires_at->getTimestamp() - jwtExpiry($plain)))->toBeLessThan(2)
        ->and($token->scopes)->toBe(['list_reports']);

    livewire(McpTokens::class)
        ->assertCanSeeTableRecords([$token]);

    passportCall('/mcp/passport', 'list_reports', [], $plain)
        ->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.content.0.text', 'report-1, report-2');

    passportCall('/mcp/passport', 'delete_report', [], $plain)
        ->assertJsonPath('result.isError', true)
        ->assertJsonPath('result.content.0.text', 'Token lacks ability manage_reports.');

    $short = app(IssuesMcpTokens::class)->issue(
        $this->user,
        'Short',
        ['list_reports'],
        now()->addSeconds(30),
    );

    expect(jwtExpiry($short))->toBeBetween(now()->addSeconds(30)->getTimestamp() - 5, now()->addSeconds(30)->getTimestamp() + 5);

    passportCall('/mcp/passport', 'list_reports', [], $short)
        ->assertJsonPath('result.content.0.text', 'report-1, report-2');

    $this->travel(2)->minutes();

    passportCall('/mcp/passport', 'list_reports', [], $short)
        ->assertOk()
        ->assertJsonPath('result.isError', true)
        ->assertJsonPath('result.content.0.text', 'Token has expired.');

    $this->travelBack();

    livewire(McpTokens::class)
        ->callTableAction('revoke', $token);

    expect($token->refresh()->revoked)->toBeTrue();

    passportCall('/mcp/passport', 'list_reports', [], $plain)->assertUnauthorized();
});

it('stores passport never as the configured lifetime on the jwt and the row', function () {
    Passport::$personalAccessTokensExpireIn = null;

    $plain = app(IssuesMcpTokens::class)->issue($this->user, 'Forever', ['list_reports'], null);
    $token = Token::query()->where('name', 'Forever')->sole();
    $exp = jwtExpiry($plain);

    expect($token->expires_at)->not->toBeNull()
        ->and($token->expires_at->getTimestamp())->toBe($exp)
        ->and($exp)->toBeGreaterThan(now()->addDays(360)->getTimestamp())
        ->and($exp)->toBeLessThan(now()->addDays(370)->getTimestamp());
});

it('lets a passport token with the star scope call every tool', function () {
    $plain = app(IssuesMcpTokens::class)->issue($this->user, 'All', ['*'], now()->addDay());

    expect(Token::query()->where('name', 'All')->sole()->scopes)->toBe(['*']);

    passportCall('/mcp/passport', 'delete_report', [], $plain)
        ->assertJsonPath('result.content.0.text', 'deleted');
});

/**
 * The passport guard keeps the first bearer for the whole test, so each call
 * starts from an empty guard and reads the token it was given.
 */
function passportCall(string $uri, string $name, array $arguments = [], ?string $token = null): TestResponse
{
    app('auth')->forgetGuards();

    return callTool($uri, $name, $arguments, $token);
}

function jwtExpiry(string $jwt): int
{
    $segment = explode('.', $jwt)[1];
    $segment .= str_repeat('=', (4 - strlen($segment) % 4) % 4);
    $payload = json_decode(base64_decode(strtr($segment, '-_', '+/')), true);

    return (int) $payload['exp'];
}
