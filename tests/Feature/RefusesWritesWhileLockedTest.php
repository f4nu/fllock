<?php

use F4nu\Fllock\Filament\Concerns\RefusesWritesWhileLocked;

/*
| The guard on its own, without a page around it. What it decides is a pure
| function of the method name, its parameters and whether the subject is held
| by somebody else, and that is the part a Filament release can quietly change:
| a field moving its reads onto a new endpoint, or a write onto an old one.
*/

function lockedGuard(bool $locked = true): object {
    return new class ($locked) {
        use RefusesWritesWhileLocked;

        public int $notified = 0;

        public function __construct(private bool $locked) {
        }

        protected function fllockIsLockedByAnother(): bool {
            return $this->locked;
        }

        protected function fllockNotifyLocked(): void {
            $this->notified++;
        }
    };
}

/** Whether the guard stopped the call. */
function refuses(object $guard, string $method, array $params = []): bool {
    $refused = false;
    $guard->callRefusesWritesWhileLocked($method, $params, function () use (&$refused) {
        $refused = true;
    });

    return $refused;
}

it('lets a field read through while the record is locked', function (string $read) {
    $guard = lockedGuard();

    expect(refuses($guard, 'callSchemaComponentMethod', ['form.logo', $read, []]))->toBeFalse()
        ->and($guard->notified)->toBe(0);
})->with([
    'file previews' => 'getUploadedFiles',
    'select options' => 'getOptionsForJs',
    'select search' => 'getSearchResultsForJs',
    'tab badges' => 'getLazyLoadedTabBadges',
]);

it('refuses a field write through the same endpoint', function (string $write) {
    $guard = lockedGuard();

    expect(refuses($guard, 'callSchemaComponentMethod', ['form.logo', $write, []]))->toBeTrue()
        ->and($guard->notified)->toBe(1);
})->with([
    'deleting a file' => 'deleteUploadedFile',
    'reordering files' => 'reorderUploadedFiles',
    'saving an attachment' => 'saveUploadedFileAttachmentAndGetUrl',
]);

it('refuses the endpoint when it cannot tell which method is meant', function () {
    expect(refuses(lockedGuard(), 'callSchemaComponentMethod', []))->toBeTrue();
});

it('refuses an ordinary write and lets the same call through once unlocked', function () {
    expect(refuses(lockedGuard(), 'save'))->toBeTrue()
        ->and(refuses(lockedGuard(locked: false), 'save'))->toBeFalse()
        ->and(refuses(lockedGuard(locked: false), 'callSchemaComponentMethod', ['form.logo', 'deleteUploadedFile', []]))->toBeFalse();
});

it('takes its list of reads from the config', function () {
    config()->set('fllock.permitted_schema_methods', []);

    expect(refuses(lockedGuard(), 'callSchemaComponentMethod', ['form.logo', 'getUploadedFiles', []]))->toBeTrue();
});
