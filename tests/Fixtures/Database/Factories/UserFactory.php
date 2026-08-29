<?php

namespace F4nu\Fllock\Tests\Fixtures\Database\Factories;

use F4nu\Fllock\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory {
    protected $model = User::class;

    public function definition(): array {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
