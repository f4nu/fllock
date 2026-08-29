<?php

namespace F4nu\Fllock\Tests\Fixtures;

use F4nu\Fllock\Models\Concerns\HasRecordLock;
use F4nu\Fllock\Tests\Fixtures\Database\Factories\PostFactory;
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

    /**
     * Named explicitly, because the default resolver prepends
     * `Database\\Factories\\` and these fixtures are loaded from a package,
     * not from an application's database directory.
     */
    protected static function newFactory(): PostFactory {
        return PostFactory::new();
    }
}
