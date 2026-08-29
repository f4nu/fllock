<?php

use F4nu\Fllock\Tests\Fixtures\User;
use F4nu\Fllock\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

function editor(string $name = 'Editor'): User {
    return User::factory()->create(['name' => $name]);
}
