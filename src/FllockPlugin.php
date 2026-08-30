<?php

namespace F4nu\Fllock;

use F4nu\Fllock\Filament\Resources\RecordLockResource;
use F4nu\Fllock\Livewire\RecordLockObserver;
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
        // A <style> tag rather than a published stylesheet: it is fifteen lines,
        // and an asset needing `filament:assets` on deploy is a step every host
        // application has to remember for a tint.
        $panel->renderHook(
            'panels::head.end',
            fn (): HtmlString => new HtmlString(static::styles()),
        );

        // By class, not by an alias registered in the service provider.
        // Registering one calls into LivewireManager while providers are still
        // booting, and if Livewire's own provider has not bound `livewire.finder`
        // yet the application fails to boot. Not just the panel: every request,
        // including the API, which is how this took a frontend down.
        $panel->renderHook(
            'panels::body.end',
            fn (): HtmlString => new HtmlString(
                Blade::render('@livewire($component)', ['component' => RecordLockObserver::class]),
            ),
        );
    }

    public function boot(Panel $panel): void {
    }

    /**
     * The row treatment for a locked record.
     *
     * Amber, not red: red is what a table uses for something that went wrong,
     * and a row somebody is editing has not gone wrong. The stripe matters as
     * much as the tint -- colour alone is no use to a reader who cannot see
     * this particular colour, and easy to miss on a crowded table besides.
     */
    protected static function styles(): string {
        return <<<'CSS'
            <style>
                .fllock-row > td:first-child,
                .fllock-row > th:first-child {
                    position: relative;
                }

                .fllock-row > td:first-child::before,
                .fllock-row > th:first-child::before {
                    content: '';
                    position: absolute;
                    inset-inline-start: 0;
                    top: 0;
                    bottom: 0;
                    width: 3px;
                }

                .fllock-row-theirs {
                    background-color: rgb(245 158 11 / 0.06);
                }

                .fllock-row-theirs > td:first-child::before,
                .fllock-row-theirs > th:first-child::before {
                    background-color: rgb(245 158 11 / 0.7);
                }

                .fllock-row-mine > td:first-child::before,
                .fllock-row-mine > th:first-child::before {
                    background-color: rgb(148 163 184 / 0.7);
                }

                .dark .fllock-row-theirs {
                    background-color: rgb(245 158 11 / 0.1);
                }
            </style>
            CSS;
    }
}
