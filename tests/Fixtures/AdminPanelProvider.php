<?php

namespace F4nu\Fllock\Tests\Fixtures;

use F4nu\Fllock\FllockPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider {
    public function panel(Panel $panel): Panel {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->spa()
            ->discoverResources(
                in: __DIR__ . '/Filament/Resources',
                for: 'F4nu\\Fllock\\Tests\\Fixtures\\Filament\\Resources',
            )
            ->discoverPages(
                in: __DIR__ . '/Filament/Pages',
                for: 'F4nu\\Fllock\\Tests\\Fixtures\\Filament\\Pages',
            )
            ->plugin(FllockPlugin::make());
    }
}
