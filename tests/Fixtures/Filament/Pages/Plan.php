<?php

namespace F4nu\Fllock\Tests\Fixtures\Filament\Pages;

use F4nu\Fllock\Filament\Concerns\LocksRecordOnPage;
use F4nu\Fllock\Tests\Fixtures\Filament\Resources\PostResource;
use F4nu\Fllock\Tests\Fixtures\Post;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Model;

/**
 * A hand-written page over one record, routed with the record in the URL and
 * writing through a method of its own — the shape a schedule planner has, and
 * the one neither the edit-page nor the settings-page guard covers.
 */
class Plan extends Page {
    use LocksRecordOnPage;

    protected string $view = 'fllock-fixtures::settings';

    public ?Post $post = null;

    public function mount(int|string $record): void {
        $this->post = Post::query()->findOrFail($record);
    }

    protected function lockedRecord(): ?Model {
        return $this->post;
    }

    /** The bespoke write: not an action, not a form, not a table. */
    public function rename(string $title): void {
        $this->post->update(['title' => $title]);
    }

    public static function getResource(): string {
        return PostResource::class;
    }
}
