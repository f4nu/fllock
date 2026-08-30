<?php

namespace F4nu\Fllock;

use F4nu\Fllock\Commands\ClearExpiredLocksCommand;
use F4nu\Fllock\Livewire\RecordLockObserver;
use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FllockServiceProvider extends PackageServiceProvider {
    public function configurePackage(Package $package): void {
        $package
            ->name('fllock')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasMigration('create_record_locks_table')
            ->hasCommand(ClearExpiredLocksCommand::class);
    }

    public function packageBooted(): void {
        Livewire::component('fllock-observer', RecordLockObserver::class);

        $this->scheduleExpiredLockSweep();
    }

    /**
     * Sweeps expired locks hourly, unless the application says not to.
     *
     * Nothing depends on this having run -- an expired lock is ignored on
     * sight, and taking a record clears whatever was there. It exists so the
     * table and the lock manager do not fill up with rows that mean nothing,
     * which is how "who is editing what" stops being readable.
     *
     * Registered here rather than left to each application's console routes,
     * because a sweep everybody has to remember is a sweep nobody runs.
     */
    protected function scheduleExpiredLockSweep(): void {
        if (! config('fllock.sweep_expired', true)) {
            return;
        }

        $this->app->booted(function (): void {
            $this->app->make(Schedule::class)
                ->command(ClearExpiredLocksCommand::class)
                ->hourly()
                ->onOneServer()
                ->runInBackground();
        });
    }
}
