<?php

namespace Tapp\FilamentMcpTokens\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Tapp\FilamentMcpTokens\FilamentMcpTokensPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(FilamentMcpTokensPlugin::make());
    }
}
