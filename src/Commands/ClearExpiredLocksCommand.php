<?php

namespace F4nu\Fllock\Commands;

use F4nu\Fllock\Models\RecordLock;
use Illuminate\Console\Command;

/**
 * Sweeps locks nobody is renewing.
 *
 * Expiry is decided by timestamp, so nothing depends on this having run -- an
 * expired lock is already ignored. It exists to keep the table and the lock
 * manager from filling with rows that mean nothing.
 */
class ClearExpiredLocksCommand extends Command {
    protected $signature = 'fllock:clear-expired';

    protected $description = 'Delete record locks nobody has renewed';

    public function handle(): int {
        $cutoff = now()->subSeconds((int) config('fllock.timeout', 120));

        $deleted = RecordLock::query()->where('updated_at', '<', $cutoff)->delete();

        $this->info("Cleared {$deleted} expired lock(s).");

        return self::SUCCESS;
    }
}
