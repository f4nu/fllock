<?php

namespace F4nu\Fllock;

use F4nu\Fllock\Commands\ClearExpiredLocksCommand;
use F4nu\Fllock\Livewire\RecordLockObserver;
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
    }
}
