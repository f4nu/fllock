<?php

namespace F4nu\Fllock\Filament\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

/**
 * Locks the record behind a hand-written page.
 *
 * `LocksRecordWhileEditing` is for an `EditRecord`, and reaches into it: its
 * form, its header actions, its save. A page routed as `/{record}/something`
 * has a record and none of that -- a schedule planner is a custom view over
 * bespoke state, saved by a method of its own.
 *
 * So this takes the lock and then refuses everything, rather than trying to
 * name the ways such a page writes. See `RefusesWritesWhileLocked`.
 *
 * The lock is the record's own, deliberately: every page that edits one event
 * -- the planner, the availabilities, the live panel, the edit form -- contends
 * for the same lock, because they are all editing that one event. Keying it on
 * the page instead would let two people work the same event from two pages, and
 * would make one person planning one event block everyone from planning any
 * other.
 */
trait LocksRecordOnPage {
    use RefusesWritesWhileLocked;

    public bool $isReadOnly = false;

    public ?string $recordLockOwner = null;

    /**
     * The record this page edits. Override when the page does not call it
     * `$record` -- plenty do not.
     */
    protected function lockedRecord(): ?Model {
        return $this->record ?? null;
    }

    #[On('fllock::init')]
    public function fllockInit(): void {
        $this->fllockSyncLock();
    }

    #[On('fllock::heartbeat')]
    public function fllockHeartbeat(): void {
        $this->fllockSyncLock();
    }

    #[On('fllock::release')]
    public function fllockRelease(): void {
        $this->lockedRecord()?->unlock();
    }

    protected function fllockSyncLock(): void {
        $record = $this->lockedRecord();

        if ($record === null || ! method_exists($record, 'lock')) {
            return;
        }

        $wasReadOnly = $this->isReadOnly;

        $record->refresh();

        if ($record->lock()) {
            $this->isReadOnly = false;
            $this->recordLockOwner = null;
        } else {
            $this->isReadOnly = true;
            $this->recordLockOwner = $record->recordLockOwnerName();
        }

        if (! $this->isReadOnly && $wasReadOnly) {
            $this->fllockDismissLockNotice();
        }

        if ($this->isReadOnly && ! $wasReadOnly) {
            $this->fllockNotifyLocked();
        }
    }

    protected function fllockIsLockedByAnother(): bool {
        $record = $this->lockedRecord();

        if ($record === null || ! method_exists($record, 'isLockedByAnotherUser')) {
            return false;
        }

        $record->refresh();

        return $record->isLockedByAnotherUser();
    }

    /**
     * Take the banner down once the page becomes writable again.
     *
     * It is persistent on purpose -- a read-only page has to keep saying why --
     * so nothing removes it when the lock is handed over, and the page ends up
     * editable underneath a notice claiming it is not.
     */
    protected function fllockDismissLockNotice(): void {
        $this->dispatch('close-notification', id: 'fllock');
    }

    protected function fllockNotifyLocked(): void {
        Notification::make('fllock')
            ->warning()
            ->persistent()
            ->title($this->recordLockOwner
                ? __('fllock::fllock.locked_by', ['name' => $this->recordLockOwner])
                : __('fllock::fllock.locked'))
            ->send();
    }

    /**
     * Nothing but the take-over survives on a locked page: a header action here
     * is as much a write as anything else, and this trait cannot tell which.
     *
     * @return array<mixed>
     */
    public function getCachedHeaderActions(): array {
        $actions = parent::getCachedHeaderActions();

        if (! $this->isReadOnly) {
            return $actions;
        }

        return array_values(array_filter(
            $actions,
            fn ($action): bool => method_exists($action, 'getName')
                && $action->getName() === 'fllockForceUnlock',
        ));
    }

    public function fllockForceUnlock(): void {
        if (! $this->fllockCanForceUnlock()) {
            return;
        }

        $this->lockedRecord()?->unlock(force: true);
        $this->fllockSyncLock();
    }

    public function fllockCanForceUnlock(): bool {
        $gate = config('fllock.unlock_gate');

        return $gate === null || Gate::allows($gate);
    }
}
