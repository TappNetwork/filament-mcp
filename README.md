# Filament MCP Tokens

A Filament page where users create Laravel Sanctum tokens for your app's MCP servers. After a user creates a token, the page shows the steps to connect an AI agent to it.

- **Abilities come from your tools.** Every tool on an MCP server behind `auth:sanctum` becomes a Sanctum ability. Add a tool to a server and it shows up on the token form. You don't maintain an ability list.
- **Tokens only reach the tools they're allowed.** A tool call is refused when the token lacks that tool's ability, and `tools/list` only shows tools the token can call.
- **Setup steps come from your routes.** The setup prompt and the JSON config use each server's name and URL, so they follow your routes.
- **Optional audit log.** When [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog) is installed, every tool call made with a token is logged.

The page builds on [filament-sanctum](https://github.com/TappNetwork/filament-sanctum), so the token table, expiry and revoking work the way they do there.

## Installation

```bash
composer require tapp/filament-mcp-tokens
php artisan filament-mcp-tokens:install
```

The install command:

1. Publishes `config/filament-mcp-tokens.php`.
2. Publishes and runs Sanctum's `personal_access_tokens` migration, unless it is already there. Pass `--no-migrate` to skip running it.
3. Adds `Laravel\Sanctum\HasApiTokens` to your user model.
4. Adds `FilamentMcpTokensPlugin` to your panel. If the panel already uses `SanctumPlugin`, it is replaced. Pass `--panel=<id>` when you have more than one panel.

You can run it again safely. It skips any step that's already done.

To add the plugin yourself:

```php
use Tapp\FilamentMcpTokens\FilamentMcpTokensPlugin;

$panel->plugin(FilamentMcpTokensPlugin::make());
```

## Registering MCP servers

Your app registers its own servers with [Laravel MCP](https://github.com/laravel/mcp). The package finds every web server behind the Sanctum guard:

```php
// routes/ai.php
use App\Mcp\Servers\ReportsServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/reports', ReportsServer::class)->middleware('auth:sanctum');
```

The package never registers a server itself. It leaves alone any server without `auth:sanctum`, and local servers (`Mcp::local`).

## Abilities

By default a tool's ability is its name. A token created with the `list_reports` ability can call the `list_reports` tool and nothing else.

To cover several tools with one ability, add the `Ability` attribute to them:

```php
use Laravel\Mcp\Server\Tool;
use Tapp\FilamentMcpTokens\Attributes\Ability;

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

Tokens created without abilities (`['*']`) can call every tool. A signed-in browser session is let through, the way Sanctum treats first-party requests.

For servers that use `ToolSearch`, each tool that `execute_tools` runs is checked against its own ability. `search_tools` and `execute_tools` stay listed.

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
php artisan vendor:publish --tag=filament-mcp-tokens-translations
```

To change the page itself, extend it and override `getSetupSteps()` or `getAbilityOptions()`, then return your page from a plugin that extends `FilamentMcpTokensPlugin`. You can also publish the view with `--tag=filament-mcp-tokens-views`.

## Audit log

When `spatie/laravel-activitylog` is installed, each tool call made with a token adds one entry, with:

- the log name `mcp` (set `audit.log_name` to change it)
- the user as the causer
- `token_id`, `tool`, `server` and `outcome` (`ok`, `error`, `refused` or `streamed`) as properties
- `ability` when the call was refused, and `calls` for `execute_tools`

A tool can add its own properties to the entry:

```php
use Tapp\FilamentMcpTokens\Facades\McpAudit;

McpAudit::with(['report_id' => $report->id]);
```

Set `audit.enabled` to `false` to turn it off.

## Configuration

`config/filament-mcp-tokens.php` controls the page:

- `navigation`: slug, icon, sidebar entry and user menu entry.
- `authorization.gate`: a gate that decides who can manage tokens. The page and its menu entries are hidden from users the gate refuses.
- `abilities`: form columns, expiry options and extra abilities.
- `audit`: whether and where tool calls are logged.

The package doesn't read or change `config/filament-sanctum.php`.

## Testing your tools

Server tests run without a signed-in user, so abilities aren't checked. To test them, act as a user with a token:

```php
use Laravel\Sanctum\Sanctum;

Sanctum::actingAs($user, ['list_reports']);

ReportsServer::tool(ListReports::class)->assertOk();
ReportsServer::tool(DeleteReport::class)->assertHasErrors(['Token lacks ability manage_reports.']);
```

A user signed in with `actingAs()` alone has no token and is refused, so use `Sanctum::actingAs()`.

## Development

```bash
composer test
composer analyse
composer format
```

## License

MIT. See [LICENSE.md](LICENSE.md).
