<?php

namespace F4nu\Fllock\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Stops a page saving over a change it never saw.
 *
 * A lock only governs the panel. The API, a console command and a queued job
 * all write whenever they like -- correctly so; an API client cannot hold a
 * lock and blocking it would break the frontend. But that leaves the panel
 * holding a form filled from a record that has since moved on, and saving it
 * puts the stale values back over the new ones, silently.
 *
 * So the page remembers what `updated_at` said when it filled the form, and
 * refuses to save if it no longer says that. Not "if it is older": a clock skew
 * between servers, or a writer setting the column explicitly, can leave the
 * current value earlier than the snapshot, and that is still somebody else's
 * write about to be overwritten.
 *
 * The form is not cleared. Ten minutes of typing thrown away because something
 * else touched the row is its own kind of data loss, and refusing the save is
 * already enough to protect the other writer. Reloading is offered instead, on
 * the notification, so discarding is a decision rather than a surprise.
 *
 * What it cannot see: a writer that does not touch `updated_at` -- a raw
 * `DB::table()->update()` -- and a write landing in the same second as the
 * snapshot, since Laravel's timestamps are second-precision.
 */
trait RefusesStaleWrites {
    /** What `updated_at` said when this form was filled. */
    public ?string $recordFingerprint = null;

    public bool $isStale = false;

    /** The record whose freshness this page is tracking. */
    abstract protected function staleCheckRecord(): ?Model;

    /** Refill the form from the database. */
    abstract protected function reloadStaleRecord(): void;

    protected function rememberRecordFingerprint(): void {
        $this->recordFingerprint = $this->currentRecordFingerprint();
        $this->isStale = false;
    }

    protected function currentRecordFingerprint(): ?string {
        $record = $this->staleCheckRecord();

        if ($record === null || ! $record->usesTimestamps()) {
            return null;
        }

        return $record->newQuery()
            ->whereKey($record->getKey())
            ->value($record->getUpdatedAtColumn())
            ?->toJSON();
    }

    /**
     * Has the record moved since this form was filled?
     */
    protected function recordChangedElsewhere(): bool {
        // Nothing to compare against: a model without timestamps, or a form
        // that never recorded one. Refusing every save would be worse than the
        // problem.
        if ($this->recordFingerprint === null) {
            return false;
        }

        return $this->currentRecordFingerprint() !== $this->recordFingerprint;
    }

    protected function notifyRecordChangedElsewhere(): void {
        Notification::make('fllock-stale')
            ->warning()
            ->persistent()
            ->title(__('fllock::fllock.stale.title'))
            ->body(__('fllock::fllock.stale.body'))
            ->actions([
                Action::make('reload')
                    ->label(__('fllock::fllock.stale.reload'))
                    ->button()
                    ->dispatch('fllock::reload'),
            ])
            ->send();
    }

    #[\Livewire\Attributes\On('fllock::reload')]
    public function fllockReload(): void {
        $this->reloadStaleRecord();
        $this->rememberRecordFingerprint();

        Notification::make()
            ->success()
            ->title(__('fllock::fllock.stale.reloaded'))
            ->send();
    }
}
