<?php

use F4nu\Fllock\Models\PageLock;
use F4nu\Fllock\Models\RecordLock;
use F4nu\Fllock\Tests\Fixtures\Post;
use F4nu\Fllock\Tests\Fixtures\UuidPost;

it('locks a record for the user who takes it', function () {
    $this->actingAs($first = editor('First'));
    $post = Post::factory()->create();

    expect($post->lock())->toBeTrue()
        ->and($post->isLocked())->toBeTrue()
        ->and($post->isLockedByCurrentUser())->toBeTrue()
        ->and($post->recordLockOwnerName())->toBe('First');

    $this->actingAs(editor('Second'));
    $post->refresh();

    expect($post->lock())->toBeFalse()
        ->and($post->isLockedByAnotherUser())->toBeTrue()
        ->and(RecordLock::query()->count())->toBe(1);
});

it('lets the holder renew without piling up rows', function () {
    $this->actingAs(editor());
    $post = Post::factory()->create();

    $post->lock();
    $post->refresh()->lock();
    $post->refresh()->lock();

    expect(RecordLock::query()->count())->toBe(1);
});

it('hands the record over once the lock lapses', function () {
    $this->actingAs(editor('First'));
    $post = Post::factory()->create();
    $post->lock();

    $post->recordLock->update([
        'updated_at' => now()->subSeconds(config('fllock.timeout') + 1),
    ]);

    $this->actingAs(editor('Second'));
    $post = $post->fresh();

    expect($post->isLocked())->toBeFalse()
        ->and($post->lock())->toBeTrue()
        ->and(RecordLock::query()->count())->toBe(1);
});

it('refuses to unlock someone else\'s record without force', function () {
    $this->actingAs(editor('First'));
    $post = Post::factory()->create();
    $post->lock();

    $this->actingAs(editor('Second'));
    $post = $post->fresh();

    expect($post->unlock())->toBeFalse()
        ->and($post->fresh()->isLocked())->toBeTrue()
        ->and($post->unlock(force: true))->toBeTrue()
        ->and($post->fresh()->isLocked())->toBeFalse();
});

it('holds a uuid key without truncating it', function () {
    $this->actingAs(editor());

    $one = UuidPost::factory()->create();
    $two = UuidPost::factory()->create();

    $one->lock();
    $two->lock();

    // morphs() is an unsignedBigInteger: both of these would land on 0 and the
    // second lock would look like the first.
    expect(RecordLock::query()->count())->toBe(2)
        ->and(RecordLock::query()->pluck('lockable_id')->all())
        ->toEqualCanonicalizing([$one->getKey(), $two->getKey()]);
});

it('clears a record\'s locks when the record is deleted', function () {
    $this->actingAs(editor());
    $post = Post::factory()->create();
    $post->lock();

    $post->delete();

    expect(RecordLock::query()->count())->toBe(0);
});

it('lets a user holding a lock be deleted', function () {
    $this->actingAs($user = editor());
    Post::factory()->create()->lock();

    $user->delete();

    expect(RecordLock::query()->count())->toBe(0);
});

it('clears expired locks from the console', function () {
    $this->actingAs(editor());
    $post = Post::factory()->create();
    $post->lock();
    $post->recordLock->update(['updated_at' => now()->subHour()]);

    $this->artisan('fllock:clear-expired')->assertSuccessful();

    expect(RecordLock::query()->count())->toBe(0);
});

it('locks a page that has no record behind it', function () {
    $this->actingAs(editor('First'));
    $lock = PageLock::for('App\Filament\Pages\Settings');

    expect($lock->lock())->toBeTrue()
        ->and($lock->isLocked())->toBeTrue()
        ->and($lock->isLockedByCurrentUser())->toBeTrue()
        ->and($lock->ownerName())->toBe('First');

    $this->actingAs(editor('Second'));

    expect($lock->lock())->toBeFalse()
        ->and($lock->isLockedByAnotherUser())->toBeTrue()
        ->and(RecordLock::query()->count())->toBe(1);
});

it('keeps two page locks apart', function () {
    $this->actingAs(editor());

    PageLock::for('One')->lock();
    PageLock::for('Two')->lock();

    expect(RecordLock::query()->count())->toBe(2)
        ->and(PageLock::for('One')->isLocked())->toBeTrue()
        ->and(PageLock::for('Two')->isLocked())->toBeTrue();
});

it('hands a page over once its lock lapses', function () {
    $this->actingAs(editor('First'));
    $lock = PageLock::for('Settings');
    $lock->lock();

    $lock->current()->update([
        'updated_at' => now()->subSeconds(config('fllock.timeout') + 1),
    ]);

    $this->actingAs(editor('Second'));

    expect(PageLock::for('Settings')->isLocked())->toBeFalse()
        ->and(PageLock::for('Settings')->lock())->toBeTrue();
});
