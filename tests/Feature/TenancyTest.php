<?php

use F4nu\Fllock\FllockPlugin;
use F4nu\Fllock\Models\RecordLock;
use F4nu\Fllock\Tests\Fixtures\Comment;
use F4nu\Fllock\Tests\Fixtures\Post;
use Filament\Facades\Filament;
use Filament\Panel;

it('leaves the lock table alone in a panel with tenancy', function () {
    // Any model will do as the tenant: what matters is that RecordLock has no
    // relationship pointing at it, as in every real application.
    $panel = Panel::make()->id('tenanted')->path('tenanted')->tenant(Comment::class)->plugin(FllockPlugin::make());
    $tenant = Comment::factory()->create();

    $this->actingAs(editor());
    $post = Post::factory()->create();
    $post->lock();

    Filament::setCurrentPanel($panel);
    Filament::setTenant($tenant);
    // Booting is what registers the tenancy scopes. bootCurrentPanel() is a
    // no-op once the fixture panel has booted, so boot this one directly.
    $panel->boot();

    expect(Post::query()->with('recordLock')->find($post->id)->isLocked())->toBeTrue()
        ->and(RecordLock::query()->count())->toBe(1);
});
