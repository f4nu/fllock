<?php

namespace F4nu\Fllock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int|string $user_id
 * @property string $lockable_type
 * @property string $lockable_id
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class RecordLock extends Model {
    protected $guarded = [];

    public function getTable(): string {
        return config('fllock.table', 'record_locks');
    }

    public function user(): BelongsTo {
        return $this->belongsTo(config('fllock.user_model'));
    }

    public function lockable(): MorphTo {
        return $this->morphTo();
    }

    public function isExpired(?int $timeout = null): bool {
        $timeout ??= config('fllock.timeout', 120);

        return $this->updated_at === null
            || $this->updated_at->addSeconds($timeout)->isPast();
    }
}
