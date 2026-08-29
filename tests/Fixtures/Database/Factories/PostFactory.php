<?php

namespace F4nu\Fllock\Tests\Fixtures\Database\Factories;

use F4nu\Fllock\Tests\Fixtures\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory {
    protected $model = Post::class;

    public function definition(): array {
        return [
            'title' => fake()->sentence(),
            'is_published' => false,
        ];
    }
}
