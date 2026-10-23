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
