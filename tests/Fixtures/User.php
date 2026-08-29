<?php

namespace F4nu\Fllock\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use F4nu\Fllock\Tests\Fixtures\Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements FilamentUser {
    use HasFactory;

    protected $guarded = [];

    public function canAccessPanel(Panel $panel): bool {
        return true;
    }

    /**
     * Named explicitly, because the default resolver prepends
     * `Database\\Factories\\` and these fixtures are loaded from a package,
     * not from an application's database directory.
     */
    protected static function newFactory(): UserFactory {
        return UserFactory::new();
    }
}
