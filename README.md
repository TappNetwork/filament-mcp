# Filament MCP

MCP for Laravel apps with a Filament panel. Users create Laravel Sanctum tokens for your app's MCP servers on a Filament page, and after creating one they get the steps to connect any AI agent to it.

- **A token page made for MCP.** Users name a token, pick its abilities and an expiry, and can see and revoke their tokens. The new token is shown once, in the response that created it.
- **Abilities come from your tools.** Every tool on an MCP server behind `auth:sanctum` becomes a Sanctum ability. Add a tool to a server and it shows up on the token form. You don't maintain an ability list.
- **Tokens only reach the tools they're allowed.** A tool call is refused when the token lacks that tool's ability, and `tools/list` only shows tools the token can call.
- **Setup steps come from your routes.** The setup prompt and the JSON config use each server's name and URL, so they follow your routes.
- **Optional audit log.** When [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog) is installed, every tool call made with a token is logged.

## Installation

Requires PHP 8.3+, Filament 4 or 5, and [Laravel MCP](https://github.com/laravel/mcp). Tokens use [Laravel Sanctum](https://github.com/laravel/sanctum) ^4 by default, which the app installs itself (`composer require laravel/sanctum`). To use [Laravel Passport](https://github.com/laravel/passport) instead, install Passport ^13, create a personal access client, and pass `--driver=passport`.

Until the package is on Packagist, add the repository to your app's `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/TappNetwork/filament-mcp" }
]
```

Then install it and run the install command:

```bash
composer require laravel/sanctum tapp/filament-mcp:dev-main
php artisan filament-mcp:install
```

For Passport ^13:

```bash
composer require laravel/passport:^13.0 tapp/filament-mcp:dev-main
php artisan passport:client --personal
php artisan filament-mcp:install --driver=passport
```

The install command:

1. Publishes `config/filament-mcp.php`. With `--driver=passport`, it sets `auth.driver` to `passport` in that file.
2. For Sanctum (the default): publishes and runs Sanctum's `personal_access_tokens` migration, unless it is already there. Pass `--no-migrate` to skip running it. Adds `Laravel\Sanctum\HasApiTokens` to your user model.
3. For Passport: does not publish a migration or edit the user model. It tells you to add `Laravel\Passport\HasApiTokens` yourself, and it warns when `php artisan passport:client --personal` has not been run.
4. Adds `FilamentMcpPlugin` to your panel. If the panel uses `SanctumPlugin` from devtical/filament-sanctum, it is replaced and that package can be removed. Pass `--panel=<id>` when you have more than one panel.

You can run it again safely. It skips any step that's already done.

To add the plugin yourself:

```php
use Tapp\FilamentMcp\FilamentMcpPlugin;

$panel->plugin(FilamentMcpPlugin::make());
```

## Registering MCP servers

Your app registers its own servers with [Laravel MCP](https://github.com/laravel/mcp). The package finds every web server behind the guard configured in `filament-mcp.auth.guards` (Sanctum's `auth:sanctum` by default):

```php
// routes/ai.php
use App\Mcp\Servers\ReportsServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/reports', ReportsServer::class)->middleware('auth:sanctum');
```

The package never registers a server itself. It leaves alone any server that is not behind a configured guard, and local servers (`Mcp::local`).

## Passport

Sanctum is the default. To issue Passport personal access tokens instead, publish the config (or pass `--driver=passport` to the install command) and set:

```php
'auth' => [
    'driver' => 'passport',
    'guards' => null, // null means the api guard
],
```

Requires `laravel/passport` ^13. The user model must use `Laravel\Passport\HasApiTokens`. Do not also add Sanctum's `HasApiTokens`: the two traits define the same methods and cannot sit on one model. Create a personal access client if you have not already (`php artisan passport:client --personal`). Register the MCP route with the Passport guard:

```php
Mcp::web('/mcp/admin', AdminServer::class)->middleware('auth:api');
```

`guards` can list more than one guard, for example `['sanctum', 'api']`, so the token page knows about servers on either middleware. Creating a token still uses `driver`. A guard such as `auth:web` can be listed too, but a session on that guard has no access token and tool calls are refused.

Passport's `createToken()` has no per-token expiry argument. For a chosen date, the package sets `Passport::personalAccessTokensExpireIn` to that moment, issues the token, and restores the previous lifetime. The JWT `exp` claim and the token row's `expires_at` are that same moment. Passport rejects the bearer when `exp` has passed, and this package also refuses a call when the row's `expires_at` is already past.

Passport cannot issue a token with no `exp`. Choosing "never", or turning expiry off, leaves Passport's personal access token lifetime in place: one year, unless the app has called `Passport::personalAccessTokensExpireIn`. The form labels that choice with the date it will actually expire. The token row stores that same expiry.

Every token this page issues also gets a `filament-mcp` scope. The page lists and revokes only tokens that have it, so an authorization-code token or a personal access token created somewhere else is left alone, refresh token included. The scope is not shown as an ability. Tokens issued before this scope was added do not have it: they keep working until they expire or are revoked outside this page, and this page will not list or revoke them.

Tool abilities are registered as Passport scopes when the token is created, alongside any scopes the app already defined. A token created with the `*` scope can call every tool. Passport already treats `*` as a defined scope, so it is not added to the app's scope list. Revoking a token sets `revoked` instead of deleting the row.

## Abilities

By default a tool's ability is its name. A token created with the `list_reports` ability can call the `list_reports` tool and nothing else.

To cover several tools with one ability, add the `Ability` attribute to them:

```php
use Laravel\Mcp\Server\Tool;
use Tapp\FilamentMcp\Attributes\Ability;

#[Ability('manage_reports', label: 'Manage reports')]
class DeleteReport extends Tool
{
    // ...
}

#[Ability('manage_reports')]
class RenameReport extends Tool
{
    // ...
}
```

The token form then offers one "Manage reports" checkbox for both tools. The label only needs to be on one tool in the group. Without one, the label is the ability name in headline case. Groups also let existing tokens keep working after you rename or split a tool.

Tokens created without abilities (`['*']`) can call every tool. A first-party Sanctum request is let through, because the `auth:sanctum` guard attaches a transient token that can call every tool. A signed-in user with no access token, including a session on `auth:web`, is refused.

For servers that use `ToolSearch`, each tool that `execute_tools` runs is checked against its own ability. `search_tools` and `execute_tools` stay listed, and `search_tools` can still name tools the token isn't allowed to run.

To offer abilities that aren't MCP tools, such as for your own API routes, list them in the config:

```php
'abilities' => [
    'extra' => [
        'reports:export' => 'Export reports',
    ],
],
```

## Setup steps

After a token is created, the page shows these steps for each server:

1. A prompt to paste into the agent. It names the server and its URL and asks the agent to collect the token securely.
2. The token, with buttons to copy it on its own or as a JSON MCP config.

The server name comes from the server's `#[Name]` attribute (or its `$name` property), and the URL comes from its route. The JSON key is the slug of the name.

To change the wording, publish the translations:

```bash
php artisan vendor:publish --tag=filament-mcp-translations
```

To change the page itself, extend it and override `getSetupSteps()` or `getAbilityOptions()`, then return your page from a plugin that extends `FilamentMcpPlugin`. You can also publish the view with `--tag=filament-mcp-views`.

## Audit log

When `spatie/laravel-activitylog` is installed, each tool call made with a token adds one entry, with:

- the log name `mcp` (set `audit.log_name` to change it)
- the user as the causer
- `token_id`, `tool`, `server` and `outcome` (`ok`, `error`, `refused` or `streamed`) as properties
- `ability` when the call was refused, and `calls` for `execute_tools`

A tool can add its own properties to the entry:

```php
use Tapp\FilamentMcp\Facades\McpAudit;

McpAudit::with(['report_id' => $report->id]);
```

Properties with the same names as the ones above are ignored, so a tool can't change what the entry says the call did. A tool that streams its response is logged as `streamed` before it runs, so properties it adds while streaming aren't recorded.

Set `audit.enabled` to `false` to turn it off.

## Configuration

`config/filament-mcp.php` controls the page:

- `navigation`: slug, icon, sidebar entry and user menu entry.
- `authorization.gate`: a gate that decides who can manage tokens. The page and its menu entries are hidden from users the gate refuses.
- `abilities`: form columns, expiry options and extra abilities.
- `audit`: whether and where tool calls are logged.

Set `abilities.allow_expiration` to `false` to create tokens that never expire.

## Testing your tools

Server tests run without a signed-in user, so abilities aren't checked. To test them, act as a user with a token:

```php
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;

Sanctum::actingAs($user, ['list_reports']);
// Passport::actingAs($user, ['list_reports']);

ReportsServer::tool(ListReports::class)->assertOk();
ReportsServer::tool(DeleteReport::class)->assertHasErrors(['Token lacks ability manage_reports.']);
```

A user signed in with `actingAs()` alone has no token and is refused. Pass the abilities or scopes the call needs to `Sanctum::actingAs()` or `Passport::actingAs()`.

## Development

```bash
composer test
composer analyse
composer format
```

## License

MIT. See [LICENSE.md](LICENSE.md).
