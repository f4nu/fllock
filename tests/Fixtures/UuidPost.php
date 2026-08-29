<?php

namespace F4nu\Fllock\Tests\Fixtures;

use F4nu\Fllock\Models\Concerns\HasRecordLock;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A uuid-keyed lockable model, because `morphs()` is an unsignedBigInteger and
 * MySQL truncates a uuid into it with a warning rather than an error -- every
 * such record then quietly shares one lock row.
 */
class UuidPost extends Model {
    use HasFactory;
    use HasRecordLock;
    use HasUuids;

    protected $guarded = [];
}
