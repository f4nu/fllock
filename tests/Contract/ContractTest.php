<?php

use F4nu\Fllock\Testing\LockingContract;
use F4nu\Fllock\Tests\Fixtures\User;

/*
| The package runs its own shipped contract against its own fixture panel.
|
| That panel is built out of the shapes that actually broke in the wild: a page
| that overrides form(), an inline toggle column, a reorderable table, a bulk
| delete and a custom row action that writes. If a Filament release moves one of
| those endpoints, this is meant to fail here rather than silently in somebody's
| admin.
|
| It does not run under Testbench. Every Livewire render there dies inside
| SupportValidation -- `$component->getErrorBag()` comes back null and the shared
| ViewErrorBag refuses it -- and that is not about locking or even about
| Filament: a bare Livewire component with an empty view fails the same way.
| Testbench is a container and a config array, not a request, and Livewire v4
| assumes a request has happened.
|
| The fix is not to make Testbench pretend harder. It is to run this against a
| scaffolded application, which is what .github/workflows/contract.yml does --
| a real Laravel app per Filament version in the matrix. The model-level suite
| beside this one stays on Testbench, where it is honest and fast.
*/

$fixtures = is_dir(__DIR__ . '/../Fixtures/Filament')
    ? __DIR__ . '/../Fixtures/Filament'
    : dirname(__DIR__, 3) . '/fllock/tests/Fixtures/Filament';

LockingContract::for($fixtures, 'F4nu\Fllock\Tests\Fixtures\Filament')
    ->editors(fn (): array => [
        User::factory()->create(['name' => 'First Editor']),
        User::factory()->create(['name' => 'Second Editor']),
    ])
    ->record(fn (string $model) => $model::factory()->create())
    ->run();
