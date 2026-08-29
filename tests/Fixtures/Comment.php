<?php

namespace F4nu\Fllock\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Deliberately not lockable: a relation manager's rows are governed by the lock
 * on the record they hang off, not by locks of their own.
 */
class Comment extends Model {
    use HasFactory;

    protected $guarded = [];

    public function post() {
        return $this->belongsTo(Post::class);
    }
}
