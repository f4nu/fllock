<?php

namespace F4nu\Fllock\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements FilamentUser {
    use HasFactory;

    protected $guarded = [];

    public function canAccessPanel(Panel $panel): bool {
        return true;
    }
}
