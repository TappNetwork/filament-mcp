<?php

namespace Tapp\FilamentMcpTokens\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;

    protected $table = 'users';

    protected $guarded = [];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
