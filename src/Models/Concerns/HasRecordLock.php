<?php

namespace F4nu\Fllock\Models\Concerns;

use F4nu\Fllock\Models\RecordLock;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Makes a model lockable while someone is editing it.
 *
 * The lock is a row keyed by the model's morph, held by a user and renewed by
 * the editor's browser; it expires on its own so a closed laptop does not hold
 * a record forever.
 */
trait HasRecordLock {
    /**
     * Deleting a record has to take its locks with it. `lockable` is a morph, so
     * the database cleans up nothing, and a row pointing at an id that no longer
     * resolves is not merely untidy: anything that reads `$lock->lockable` gets
     * null where it expects a model. Deleting from an edit page is the ordinary
     * way to hit this, since the page holds a lock on what it is deleting.
     */
    public static function bootHasRecordLock(): void {
        static::deleted(function ($model): void {
            RecordLock::query()
                ->where('lockable_type', $model->getMorphClass())
                ->where('lockable_id', $model->getKey())
                ->delete();
        });
    }

    public function recordLock(): MorphOne {
        return $this->morphOne(RecordLock::class, 'lockable')->latestOfMany();
    }

    /** Seconds before an unrenewed lock lapses. Override per model. */
    public function recordLockTimeout(): int {
        return property_exists($this, 'recordLockTimeout')
            ? $this->recordLockTimeout
            : config('fllock.timeout', 120);
    }

    public function isLocked(): bool {
        $lock = $this->recordLock;

        return $lock !== null && $lock->exists && ! $lock->isExpired($this->recordLockTimeout());
    }

    public function isLockedByCurrentUser(): bool {
        return $this->isLocked() && $this->recordLock->user_id === auth()->id();
    }

    public function isLockedByAnotherUser(): bool {
        return $this->isLocked() && ! $this->isLockedByCurrentUser();
    }

    /**
     * Take the lock, or renew it if it is already ours.
     *
     * @return bool false when someone else holds it
     */
    public function lock(): bool {
        if ($this->isLockedByAnotherUser()) {
            return false;
        }

        if ($this->isLockedByCurrentUser()) {
            $this->recordLock->touch();

            return true;
        }

        // Either unlocked or holding an expired row; either way it is ours now.
        $this->recordLock()->delete();
        $this->unsetRelation('recordLock');

        $this->recordLock()->create(['user_id' => auth()->id()]);
        $this->unsetRelation('recordLock');

        return true;
    }

    public function unlock(bool $force = false): bool {
        if (! $this->isLocked()) {
            $this->recordLock()->delete();
            $this->unsetRelation('recordLock');

            return true;
        }

        if (! $force && ! $this->isLockedByCurrentUser()) {
            return false;
        }

        $this->recordLock()->delete();
        $this->unsetRelation('recordLock');

        return true;
    }

    /** The name to show for whoever holds the lock. */
    public function recordLockOwnerName(): ?string {
        $owner = $this->recordLock?->user;

        if ($owner === null) {
            return null;
        }

        $attribute = config('fllock.owner_name_attribute', 'name');

        return $owner->{$attribute} ?? null;
    }
}
