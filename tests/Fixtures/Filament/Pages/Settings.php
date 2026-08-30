<?php

namespace F4nu\Fllock\Tests\Fixtures\Filament\Pages;

use F4nu\Fllock\Filament\Concerns\LocksPageWhileEditing;
use F4nu\Fllock\Tests\Fixtures\Post;
use Filament\Pages\Page;

/**
 * A settings page shaped like the one that broke: no record behind it, and a
 * write that happens through a bare Livewire property rather than a form or an
 * action.
 */
class Settings extends Page {
    use LocksPageWhileEditing;

    protected string $view = 'fllock-fixtures::settings';

    public ?string $probe = null;

    /** Writes on update, the way a real settings page does. */
    public function updatedProbe(): void {
        Post::query()->update(['title' => (string) $this->probe]);
    }
}
