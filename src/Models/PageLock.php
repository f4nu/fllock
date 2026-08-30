<?php

namespace F4nu\Fllock\Models;

/**
 * A lock on something that is not a record.
 *
 * Settings pages -- Twitch credentials, site globals, a stream control panel -- have
 * no model row to hang a lock off, and they are exactly the pages where a
 * second editor silently clobbers the first: everything is one form over one
 * singleton, so last write wins and nothing tells anybody.
 *
 * The lock row is the same one records use, with the page's own class as the
 * morph type and a fixed key, so the lock manager lists both kinds together.
 */
class PageLock {
    public const KEY = 'singleton';

    public function __construct(
        protected string $subject,
    ) {
    }

    public static function for(string $subject): self {
        return new self($subject);
    }

    public function current(): ?RecordLock {
        return RecordLock::query()
            ->where('lockable_type', $this->subject)
            ->where('lockable_id', self::KEY)
            ->latest('id')
            ->first();
    }

    public function isLocked(): bool {
        $lock = $this->current();

        return $lock !== null && ! $lock->isExpired();
    }

    public function isLockedByCurrentUser(): bool {
        return $this->isLocked() && $this->current()->user_id === auth()->id();
    }

    public function isLockedByAnotherUser(): bool {
        return $this->isLocked() && ! $this->isLockedByCurrentUser();
    }

    /** @return bool false when someone else holds it */
    public function lock(): bool {
        if ($this->isLockedByAnotherUser()) {
            return false;
        }

        if ($this->isLockedByCurrentUser()) {
            $this->current()->touch();

            return true;
        }

        $this->clear();

        RecordLock::query()->create([
            'user_id' => auth()->id(),
            'lockable_type' => $this->subject,
            'lockable_id' => self::KEY,
        ]);

        return true;
    }

    public function unlock(bool $force = false): bool {
        if ($this->isLocked() && ! $force && ! $this->isLockedByCurrentUser()) {
            return false;
        }

        $this->clear();

        return true;
    }

    public function ownerName(): ?string {
        $owner = $this->current()?->user;

        if ($owner === null) {
            return null;
        }

        return $owner->{config('fllock.owner_name_attribute', 'name')} ?? null;
    }

    protected function clear(): void {
        RecordLock::query()
            ->where('lockable_type', $this->subject)
            ->where('lockable_id', self::KEY)
            ->delete();
    }
}
