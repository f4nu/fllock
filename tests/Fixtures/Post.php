<?php

namespace F4nu\Fllock\Tests\Fixtures;

use F4nu\Fllock\Models\Concerns\HasRecordLock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model {
    use HasFactory;
    use HasRecordLock;

    protected $guarded = [];

    protected $casts = ['is_published' => 'bool'];

    public function comments() {
        return $this->hasMany(Comment::class);
    }
}
