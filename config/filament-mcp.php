<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | The page is hidden from users the authorization gate refuses, in the
    | sidebar and in the user menu.
    |
    */
    'navigation' => [
        'slug' => 'api-tokens',
        'icon' => 'heroicon-o-key',

        'sidebar_menu' => [
            'enabled' => false,
            'sort' => null,
            'group' => null,
        ],

        'user_menu' => [
            'enabled' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tokens
    |--------------------------------------------------------------------------
    |
    | Which personal-access-token system creates the tokens on the Filament
    | page and which route guards mark an MCP server as managed here.
    |
    | driver: "sanctum" (default) or "passport". An app uses one driver.
    | Sanctum (^4) and Passport (^13) are installed separately; this package
    | requires neither. The user model can use Sanctum's HasApiTokens or
    | Passport's, not both, because the two traits define the same methods.
    |
    | guards: auth guards this package looks for on MCP routes. Null uses the
    | driver default (sanctum => ["sanctum"], passport => ["api"]). Set both,
    | for example ["sanctum", "api"], to manage servers registered on either
    | guard. Token creation still uses "driver".
    |
    */
    'auth' => [
        'driver' => 'sanctum',
        'guards' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Name a gate to limit who can create tokens. Leave it null to let every
    | panel user manage their own tokens.
    |
    */
    'authorization' => [
        'enabled' => true,
        'gate' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Abilities
    |--------------------------------------------------------------------------
    |
    | Every tool on a Sanctum-guarded MCP server becomes an ability on its
    | own. List abilities that aren't MCP tools under "extra".
    |
    */
    'abilities' => [
        'columns' => 2,
        'allow_expiration' => true,
        'default_expiration_days' => 30,
        'expiration_presets' => [7, 30, 60, 90],
        'extra' => [
            // 'reports:read' => 'Read reports',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit log
    |--------------------------------------------------------------------------
    |
    | When spatie/laravel-activitylog is installed, every tool call made with
    | a token is logged under this log name.
    |
    */
    'audit' => [
        'enabled' => true,
        'log_name' => 'mcp',
    ],

];
