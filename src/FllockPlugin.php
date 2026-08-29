<?php

namespace F4nu\Fllock;

use F4nu\Fllock\Filament\Resources\RecordLockResource;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Registers the heartbeat and, optionally, the lock manager.
 *
 * Everything else is configuration in config/fllock.php rather than a fluent
 * setter per option: a panel plugin whose API is fifty chainable methods is
 * fifty things to keep in step with a config file that already says the same.
 */
class FllockPlugin implements Plugin {
    public static function make(): static {
        return app(static::class);
    }

    public function getId(): string {
        return 'fllock';
    }

    public function register(Panel $panel): void {
        if (config('fllock.manager.enabled', true)) {
            $panel->resources([RecordLockResource::class]);
        }

        // Blade's @livewire, not Livewire::mount(): mount() renders the
        // component out of band, which trips Livewire's validation support when
        // it happens inside another component's render -- every Filament page
        // then dies on render under a Livewire test.
        $panel->renderHook(
            'panels::body.end',
            fn (): HtmlString => new HtmlString(Blade::render('@livewire(\'fllock-observer\')')),
        );
    }

    public function boot(Panel $panel): void {
    }
}
