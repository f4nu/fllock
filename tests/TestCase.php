<?php

namespace F4nu\Fllock\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use F4nu\Fllock\FllockServiceProvider;
use F4nu\Fllock\Tests\Fixtures\AdminPanelProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra {
    protected function setUp(): void {
        parent::setUp();

        // Livewire shares the view's `errors` bag on every render and Testbench
        // starts without one, so any Filament page blows up on render before a
        // single assertion runs. Nothing to do with locking; it just has to be
        // there.
        Filament::setCurrentPanel('admin');

        $this->startSession();
        view()->share('errors', (new ViewErrorBag())->put('default', new MessageBag()));

    }

    protected function getPackageProviders($app): array {
        return [
            ActionsServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FllockServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void {
        $app['config']->set('database.default', 'testing');
        // SQLite ignores foreign keys unless asked. Without this the cascade on
        // user_id is never exercised and a migration that forgot it would pass.
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
        $app['config']->set('auth.providers.users.model', Fixtures\User::class);
        $app['config']->set('fllock.user_model', Fixtures\User::class);
    }

    protected function defineDatabaseMigrations(): void {
        // The package's own migration is in there too, timestamped after the
        // fixtures so the users table exists for it to constrain against.
        $this->loadMigrationsFrom(__DIR__ . '/Fixtures/Database/migrations');
    }

}
