<?php

namespace F4nu\Fllock\Tests\Fixtures\Database\Factories;

use F4nu\Fllock\Tests\Fixtures\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory {
    protected $model = Comment::class;

    public function definition(): array {
        return [
            'post_id' => \F4nu\Fllock\Tests\Fixtures\Post::factory(),
            'body' => fake()->sentence(),
            'approved' => false,
        ];
    }
}
