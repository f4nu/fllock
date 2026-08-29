<?php

namespace F4nu\Fllock\Tests\Fixtures\Database\Factories;

use F4nu\Fllock\Tests\Fixtures\UuidPost;
use Illuminate\Database\Eloquent\Factories\Factory;

class UuidPostFactory extends Factory {
    protected $model = UuidPost::class;

    public function definition(): array {
        return [
            'title' => fake()->sentence(),
        ];
    }
}
