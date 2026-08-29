<?php

namespace F4nu\Fllock\Tests\Fixtures;

use F4nu\Fllock\Models\Concerns\HasRecordLock;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use F4nu\Fllock\Tests\Fixtures\Database\Factories\UuidPostFactory;
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

    /**
     * Named explicitly, because the default resolver prepends
     * `Database\\Factories\\` and these fixtures are loaded from a package,
     * not from an application's database directory.
     */
    protected static function newFactory(): UuidPostFactory {
        return UuidPostFactory::new();
    }
}
