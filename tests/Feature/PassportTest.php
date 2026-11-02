<?php

use Filament\Facades\Filament;
use Illuminate\Support\Str;
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
use Tapp\FilamentMcp\Tokens\PassportTokenIssuer;

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
        ->and($token->scopes)->toBe(['list_reports', PassportTokenIssuer::MARKER])
        ->and(app(IssuesMcpTokens::class)->abilities($token))->toBe(['list_reports']);

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

it('does not list or revoke oauth tokens this page did not issue', function () {
    app(IssuesMcpTokens::class)->issue($this->user, 'Claude', ['list_reports'], now()->addDay());

    $mcp = Token::query()->where('name', 'Claude')->sole();

    $personal = $this->user->createToken('CI script', ['*'])->token;

    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Third party', ['https://example.com/callback']);
    $oauth = Passport::token()->forceCreate([
        'id' => Str::random(40),
        'user_id' => $this->user->getKey(),
        'client_id' => $client->getKey(),
        'name' => 'Third-party app',
        'scopes' => ['read'],
        'revoked' => false,
        'expires_at' => now()->addDay(),
    ]);
    $refresh = Passport::refreshToken()->forceCreate([
        'id' => Str::random(40),
        'access_token_id' => $oauth->getKey(),
        'revoked' => false,
        'expires_at' => now()->addMonth(),
    ]);

    livewire(McpTokens::class)
        ->assertCanSeeTableRecords([$mcp])
        ->assertCanNotSeeTableRecords([$personal, $oauth]);

    $issuer = app(IssuesMcpTokens::class);
    $issuer->revoke($personal);
    $issuer->revoke($oauth);

    expect($personal->refresh()->revoked)->toBeFalse()
        ->and($oauth->refresh()->revoked)->toBeFalse()
        ->and($refresh->refresh()->revoked)->toBeFalse();

    livewire(McpTokens::class)->callTableAction('revoke', $mcp);

    expect($mcp->refresh()->revoked)->toBeTrue();
});

it('labels passport never with the personal access token lifetime', function () {
    $this->freezeSecond();

    $label = (fn () => $this->getExpirationOptions())->call(new McpTokens)['never'];

    expect($label)->toBe(__('filament-mcp::tokens.passport_lifetime', [
        'date' => now()->addYear()->format('M j, Y'),
    ]));

    Passport::$personalAccessTokensExpireIn = new DateInterval('P30D');

    $label = (fn () => $this->getExpirationOptions())->call(new McpTokens)['never'];

    expect($label)->toBe(__('filament-mcp::tokens.passport_lifetime', [
        'date' => now()->addDays(30)->format('M j, Y'),
    ]));
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

    expect(Token::query()->where('name', 'All')->sole()->scopes)->toBe(['*', PassportTokenIssuer::MARKER]);

    passportCall('/mcp/passport', 'delete_report', [], $plain)
        ->assertJsonPath('result.content.0.text', 'deleted');
});

it('adds a scope to an existing passport token, which then calls both tools', function () {
    $page = livewire(McpTokens::class)
        ->callAction('new', ['name' => 'Agent', 'expiration' => '30', 'abilities' => ['list_reports']]);

    $plain = $page->get('plainTextToken');
    $token = Token::query()->where('name', 'Agent')->sole();

    passportCall('/mcp/passport', 'list_reports', [], $plain)->assertJsonPath('result.content.0.text', 'report-1, report-2');
    passportCall('/mcp/passport', 'delete_report', [], $plain)->assertJsonPath('result.content.0.text', 'Token lacks ability manage_reports.');

    $this->actingAs($this->user);

    livewire(McpTokens::class)
        ->mountTableAction('edit', $token)
        ->assertTableActionDataSet(['abilities' => ['list_reports']])
        ->setTableActionData(['abilities' => ['list_reports', 'manage_reports']])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect(Token::query()->where('name', 'Agent')->sole())
        ->id->toBe($token->id)
        ->scopes->toBe(['list_reports', 'manage_reports', PassportTokenIssuer::MARKER]);

    passportCall('/mcp/passport', 'list_reports', [], $plain)->assertJsonPath('result.content.0.text', 'report-1, report-2');
    passportCall('/mcp/passport', 'delete_report', [], $plain)->assertJsonPath('result.content.0.text', 'deleted');

    $names = collect(mcpAs('/mcp/passport', 'tools/list', $plain)->json('result.tools'))->pluck('name')->all();

    expect($names)->toBe(['list_reports', 'delete_report', 'rename_report']);
});

it('stops a passport token from calling a tool once its scope is removed', function () {
    $plain = app(IssuesMcpTokens::class)->issue($this->user, 'Agent', ['list_reports', 'manage_reports'], now()->addDay());
    $token = Token::query()->where('name', 'Agent')->sole();

    livewire(McpTokens::class)
        ->callTableAction('edit', $token, ['abilities' => ['manage_reports']])
        ->assertHasNoTableActionErrors();

    passportCall('/mcp/passport', 'list_reports', [], $plain)->assertJsonPath('result.content.0.text', 'Token lacks ability list_reports.');
    passportCall('/mcp/passport', 'delete_report', [], $plain)->assertJsonPath('result.content.0.text', 'deleted');
});

it('leaves the scopes of oauth tokens this page did not issue to the jwt', function () {
    $plain = $this->user->createToken('CI script', ['list_reports'])->accessToken;
    $token = Token::query()->where('name', 'CI script')->sole();

    app(IssuesMcpTokens::class)->updateAbilities($token, ['manage_reports']);

    expect($token->refresh()->scopes)->toBe(['list_reports']);

    passportCall('/mcp/passport', 'list_reports', [], $plain)->assertJsonPath('result.content.0.text', 'report-1, report-2');
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

function mcpAs(string $uri, string $method, string $token): TestResponse
{
    app('auth')->forgetGuards();

    return mcp($uri, $method, [], $token);
}
