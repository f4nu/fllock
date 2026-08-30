<?php

namespace F4nu\Fllock\Testing;

use Closure;
use Filament\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\Resource;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ManageRecords;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use ReflectionClass;

/**
 * The enumerated locking test suite, declared into a host application's Pest run.
 *
 * This exists because the first application to use these guards shipped them
 * broken. The traits went onto roughly thirty classes and three were opened by
 * hand, all easy ones; both bugs that came back were on the one page nobody had
 * touched, and a third -- uuid-keyed records silently sharing a lock row -- only
 * turned up once the tests stopped naming resources and started enumerating them.
 *
 * Sampling does not work here. The guards are uniform, the surfaces are not, and
 * the resource that breaks is always the one with the unusual table. So this
 * discovers every record page, list page and relation manager from the
 * filesystem and drives each one. A resource added later is covered without
 * anybody remembering to add it; a resource that forgets a trait fails by name.
 *
 * Requires Pest: the suite is declared through its functional API.
 *
 * Usage, in one test file:
 *
 *     use F4nu\Fllock\Testing\LockingContract;
 *
 *     LockingContract::for(app_path('Filament'), 'App\Filament')
 *         ->editors(fn () => [makeAdmin(), makeAdmin()])
 *         ->record(fn (string $model) => $model::factory()->create())
 *         ->run();
 */
class LockingContract {
    /** @var Closure(): array{0: Model, 1: Model} */
    protected Closure $editors;

    /** @var Closure(string): Model */
    protected Closure $record;

    /** @var array<string> */
    protected array $skip = [];

    final public function __construct(
        protected string $path,
        protected string $namespace,
    ) {
    }

    public static function for(string $path, string $namespace): static {
        return new static(rtrim($path, '/'), trim($namespace, '\\'));
    }

    /**
     * Two authenticated users: the one who takes the lock and the one kept out.
     *
     * @param  Closure(): array{0: Model, 1: Model}  $callback
     */
    public function editors(Closure $callback): static {
        $this->editors = $callback;

        return $this;
    }

    /**
     * A saved record of the given model, ready to be opened.
     *
     * Some models cannot stand on their own -- a bid belongs to an event, a list
     * that opens on a tab needs a record of that tab's type -- so the host owns
     * this rather than the package guessing at factories.
     *
     * @param  Closure(string): Model  $callback
     */
    public function record(Closure $callback): static {
        $this->record = $callback;

        return $this;
    }

    /**
     * Classes to leave alone, by basename.
     *
     * Use it for a page that genuinely must not lock, and say why in the call:
     * an unexplained exclusion here is how a surface goes quietly uncovered.
     *
     * @param  array<string>  $classes
     */
    public function except(array $classes): static {
        $this->skip = $classes;

        return $this;
    }

    // ---------------------------------------------------------------- discovery
    //
    // Reflection only. Pest builds datasets before the application boots, so
    // nothing here may call `app_path()`, resolve the container, or ask a
    // resource for its pages -- the class list has to come off the filesystem.

    /** @return array<string, array{0: class-string}> */
    public function editPages(): array {
        return $this->classesExtending(EditRecord::class);
    }

    /** @return array<string, array{0: class-string}> */
    public function listPages(): array {
        return $this->classesExtending(ListRecords::class)
            + $this->classesExtending(ManageRecords::class);
    }

    /**
     * Custom panel pages — the ones that are not a resource's list or edit page.
     *
     * These are enumerated because they are the third distinct shape a lock has
     * to cover and the one nobody thinks of: a settings page is a single form
     * over a singleton, with no record to attach a lock to, and it is where a
     * second editor's save silently overwrites the first with no sign anywhere
     * that it happened.
     *
     * Every one of them must either lock or be named in `except()`, which makes
     * "this page does not need locking" a decision somebody wrote down rather
     * than a page nobody looked at.
     *
     * @return array<string, array{0: class-string}>
     */
    public function customPages(): array {
        $pages = array_diff_key(
            $this->classesExtending(Page::class),
            $this->editPages() + $this->listPages(),
        );

        return array_filter($pages, function (array $row): bool {
            $class = $row[0];

            // Nothing to lock on a create page: the record does not exist yet.
            // A view page writes nothing. And a page that carries a record is
            // covered by the record lock, not this one.
            return ! is_subclass_of($class, CreateRecord::class)
                && ! is_subclass_of($class, ViewRecord::class)
                && ! in_array(InteractsWithRecord::class, class_uses_recursive($class), true)
                && ! $this->mountsARecord($class);
        });
    }

    /**
     * Whether a page takes a record in `mount()`.
     *
     * Filament's own record pages say so through `InteractsWithRecord`, but a
     * hand-written page routed as `/{record}/something` resolves it itself and
     * says nothing. It is still a record page, and a record lock is what it
     * wants -- not the singleton lock this group is about.
     */
    protected function mountsARecord(string $class): bool {
        if (! method_exists($class, 'mount')) {
            return false;
        }

        foreach ((new \ReflectionMethod($class, 'mount'))->getParameters() as $parameter) {
            if ($parameter->getName() === 'record') {
                return true;
            }
        }

        return false;
    }

    /**
     * Hand-written pages that carry a record: `/{record}/planning` and the like.
     *
     * Neither an `EditRecord` nor a settings page, and so covered by neither
     * group — which is exactly why they went unlocked. They want the record's
     * own lock, so that every page editing one record contends for one lock.
     *
     * @return array<string, array{0: class-string}>
     */
    public function recordPages(): array {
        $pages = array_diff_key(
            $this->classesExtending(Page::class),
            $this->editPages() + $this->listPages(),
        );

        return array_filter($pages, function (array $row): bool {
            $class = $row[0];

            // ViewRecord is not excluded. A "view" page routinely carries
            // actions that write -- approve, mark as read, ban -- and calling
            // itself a view page says nothing about that. What counts is
            // whether it exposes an action that is not a read, which the check
            // below works out by asking the page.
            return ! is_subclass_of($class, CreateRecord::class)
                && $this->mountsARecord($class);
        });
    }

    /** @return array<string, array{0: class-string}> */
    public function relationManagers(): array {
        return $this->classesExtending(RelationManager::class);
    }

    /**
     * Relation managers that register header actions, which is what the header
     * action check needs. Built from a static read of the class rather than by
     * booting one, because datasets are built before the application is.
     *
     * @return array<string, array{0: class-string}>
     */
    public function relationManagersWithHeaderActions(): array {
        return array_filter(
            $this->relationManagers(),
            function (array $row): bool {
                $file = (new ReflectionClass($row[0]))->getFileName();

                return $file !== false && str_contains((string) file_get_contents($file), 'headerActions');
            },
        );
    }

    /**
     * Every class under the given path that extends $base.
     *
     * Walked recursively and filtered by what a class *is*, not by the folder
     * it sits in: panels nest resources to different depths, and a suite that
     * only looks two levels down silently covers nothing in the third.
     *
     * @param  class-string  $base
     * @return array<string, array{0: class-string}>
     */
    protected function classesExtending(string $base): array {
        $classes = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->path, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = $this->namespace . '\\' . str_replace('/', '\\', substr(
                $file->getPathname(),
                strlen($this->path) + 1,
                -strlen('.php'),
            ));

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf($base) || $this->skipped($class)) {
                continue;
            }

            $classes[class_basename($class)] = [$class];
        }

        ksort($classes);

        return $classes;
    }

    protected function skipped(string $class): bool {
        return in_array(class_basename($class), $this->skip, true);
    }

    // ------------------------------------------------------------------- checks

    /**
     * Declares the suite. Call it at the top level of a Pest test file.
     */
    public function run(): void {
        $contract = $this;

        // A panel need not have every kind of surface -- plenty have no
        // hand-written record page, or no relation managers at all. Pest treats
        // an empty dataset as a broken test rather than a vacuous one, so a
        // group with nothing in it is not declared.
        $when = fn (array $rows, callable $declare) => $rows === [] ? null : $declare($rows);

        describe('record locking', function () use ($contract, $when) {
            $when($contract->editPages(), function (array $rows) use ($contract) {
                it('locks a record while its edit page is open', function (string $page) use ($contract) {
                    [$first] = $contract->makeEditors();
                    $record = $contract->makeRecord($page::getResource()::getModel());

                    $contract->openEditPage($page, $record, $first);

                    expect($record->fresh()->isLocked())->toBeTrue();
                })->with($rows);
            });

            $when($contract->editPages(), function (array $rows) use ($contract) {
                it('survives the heartbeat that renews the lock', function (string $page) use ($contract) {
                    [$first] = $contract->makeEditors();
                    $record = $contract->makeRecord($page::getResource()::getModel());

                    // Nothing in an ordinary Livewire test beats, so a listener that
                    // throws stays invisible until a real page has been open for one
                    // interval. That is how it shipped the first time.
                    $contract->openEditPage($page, $record, $first)
                        ->dispatch('fllock::heartbeat')
                        ->assertSet('isReadOnly', false)
                        ->assertHasNoErrors();

                    expect($record->fresh()->isLocked())->toBeTrue();
                })->with($rows);
            });

            $when($contract->editPages(), function (array $rows) use ($contract) {
                it('goes read-only for a second editor, with no header actions left', function (string $page) use ($contract) {
                    [$first, $second] = $contract->makeEditors();
                    $record = $contract->makeRecord($page::getResource()::getModel());

                    $contract->openEditPage($page, $record, $first);

                    $component = $contract->openEditPage($page, $record, $second)
                        ->assertSet('isReadOnly', true);

                    // Named, not iterated: `foreach` over an empty array asserts
                    // nothing, which is how a missing button went unnoticed.
                    //
                    // Nothing survives in the header. The take-over is offered on
                    // the notification instead: a header action has to be
                    // registered through the page's own `getHeaderActions()`,
                    // every page here defines that itself, and a class method
                    // beats a trait one, so the action never existed.
                    $headerActions = collect($component->instance()->getCachedHeaderActions())
                        ->map(fn ($action): string => $action->getName())
                        ->all();

                    expect($headerActions)->toBe([]);

                    // And Save must be gone, not merely inert: a page that looks
                    // editable and refuses on click gives no feedback at all.
                    $formActions = (fn () => $this->getFormActions())
                        ->call($component->instance());

                    expect($formActions)->toBe([]);
                })->with($rows);
            });

            $when($contract->editPages(), function (array $rows) use ($contract) {
                it('stays disabled when the schema is rebuilt', function (string $page) use ($contract) {
                    [$first, $second] = $contract->makeEditors();
                    $record = $contract->makeRecord($page::getResource()::getModel());

                    $contract->openEditPage($page, $record, $first);

                    $component = $contract->openEditPage($page, $record, $second);

                    // Switching relation manager tabs rebuilds the schema. Disable
                    // the form imperatively and every field comes back to life here.
                    $component->set('activeRelationManager', 0)->assertSet('isReadOnly', true);

                    expect($component->instance()->form->isDisabled())->toBeTrue();
                })->with($rows);
            });

            $when($contract->editPages(), function (array $rows) use ($contract) {
                it('hands the record over when the take-over is used', function (string $page) use ($contract) {
                    [$first, $second] = $contract->makeEditors();
                    $record = $contract->makeRecord($page::getResource()::getModel());

                    $contract->openEditPage($page, $record, $first);

                    // What the notification's button dispatches. Asserting the
                    // button existed proved nothing: it never did.
                    $contract->openEditPage($page, $record, $second)
                        ->assertSet('isReadOnly', true)
                        ->dispatch('fllock::take-over')
                        ->assertSet('isReadOnly', false);

                    expect($record->fresh()->isLockedByCurrentUser())->toBeTrue();
                })->with($rows);

                it('refuses a save over a write it never saw', function (string $page) use ($contract) {
                    [$first] = $contract->makeEditors();
                    $record = $contract->makeRecord($page::getResource()::getModel());

                    $component = $contract->openEditPage($page, $record, $first);

                    // Something outside the panel writes: the API, a command, a
                    // job. None of them hold the lock, and none of them should.
                    $record->forceFill([
                        $record->getUpdatedAtColumn() => now()->addMinute(),
                    ])->saveQuietly();

                    $before = $record->fresh()->updated_at?->toJSON();

                    $component->call('save')->assertSet('isStale', true);

                    // The stale form must not have gone over the top of it.
                    expect($record->fresh()->updated_at?->toJSON())->toBe($before);
                })->with($rows);

                it('refuses a save from a second editor', function (string $page) use ($contract) {
                    [$first, $second] = $contract->makeEditors();
                    $record = $contract->makeRecord($page::getResource()::getModel());

                    $contract->openEditPage($page, $record, $first);

                    $before = $record->fresh()->updated_at;
                    $contract->openEditPage($page, $record, $second)->call('save');

                    expect($record->fresh()->updated_at?->toString())->toBe($before?->toString());
                })->with($rows);
            });

            $when($contract->listPages(), function (array $rows) use ($contract) {
                it('refuses a row modal on a locked record', function (string $page) use ($contract) {
                    [$first, $second] = $contract->makeEditors();
                    $record = $contract->makeRecord($page::getResource()::getModel());

                    $contract->actingAs($first);
                    $record->lock();

                    $contract->actingAs($second);
                    Livewire::test($page)
                        ->call('mountAction', 'edit', [], [
                            'recordKey' => (string) $record->getKey(),
                            'table' => true,
                        ])
                        ->assertSet('mountedActions', []);
                })->with($rows);
            });

            $when($contract->listPages(), function (array $rows) use ($contract) {
                it('keeps an open modal\'s lock alive on the heartbeat', function (string $page) use ($contract) {
                    [$first] = $contract->makeEditors();
                    $record = $contract->makeRecord($page::getResource()::getModel());

                    $contract->actingAs($first);

                    $component = Livewire::test($page)->call('mountAction', 'edit', [], [
                        'recordKey' => (string) $record->getKey(),
                        'table' => true,
                    ]);

                    // A modal's lock is tied to the modal, not the page, and
                    // nothing renewed it: a slide-over left open went stale
                    // after the timeout while still looking editable.
                    $contract->age($record, config('fllock.timeout') - 5);

                    $component->dispatch('fllock::heartbeat');

                    expect($record->fresh()->isLocked())->toBeTrue();
                    expect($record->fresh()->recordLock->isExpired())->toBeFalse();
                })->with($rows);

                it('refuses an inline column write on a locked row', function (string $page) use ($contract) {
                    [$first, $second] = $contract->makeEditors();
                    $record = $contract->makeRecord($page::getResource()::getModel());

                    $contract->actingAs($first);
                    $record->lock();

                    $before = $record->fresh()->updated_at?->toString();

                    // Whether this table has an editable column today or not, the
                    // endpoint has to refuse: one added later must not reopen it.
                    $contract->actingAs($second);
                    Livewire::test($page)->call(
                        'updateTableColumnState',
                        $contract->probeColumn($record),
                        (string) $record->getKey(),
                        true,
                    );

                    expect($record->fresh()->updated_at?->toString())->toBe($before);
                })->with($rows);
            });

            $when($contract->listPages(), function (array $rows) use ($contract) {
                it('refuses reordering that touches a locked row', function (string $page) use ($contract) {
                    [$first, $second] = $contract->makeEditors();
                    $record = $contract->makeRecord($page::getResource()::getModel());

                    $contract->actingAs($first);
                    $record->lock();

                    $before = $record->fresh()->updated_at?->toString();

                    $contract->actingAs($second);
                    Livewire::test($page)->call('reorderTable', [(string) $record->getKey()]);

                    expect($record->fresh()->updated_at?->toString())->toBe($before);
                })->with($rows);
            });
            $when($contract->customPages(), function (array $rows) use ($contract) {
                it('refuses a bare property write on a locked settings page', function (string $page) use ($contract) {
                    [$first, $second] = $contract->makeEditors();

                    $contract->actingAs($first);
                    Livewire::test($page)->dispatch('fllock::init');

                    // Not an action, not a form, not a table: a public property with
                    // an updated hook is arbitrary application code, and every other
                    // guard is blind to it. Livewire runs trait hooks before the
                    // component's own, which is the only reason this can be stopped.
                    $contract->actingAs($second);
                    Livewire::test($page)
                        ->dispatch('fllock::init')
                        ->assertSet('isReadOnly', true);
                })->with($rows);
            });

            $when($contract->relationManagersWithHeaderActions(), function (array $rows) use ($contract) {
                it('refuses every header action on a relation manager whose record is locked', function (string $manager) use ($contract) {
                    [$first, $second] = $contract->makeEditors();
                    $owner = $contract->makeRecord($contract->ownerModelOf($manager));

                    $contract->actingAs($first);
                    $owner->lock();

                    $contract->actingAs($second);

                    $mount = fn (): \Livewire\Features\SupportTesting\Testable => Livewire::test($manager, [
                        'ownerRecord' => $owner,
                        'pageClass' => $contract->pageClassFor($manager),
                    ]);

                    // The manager's own header actions, by name. Guessing at 'create'
                    // would pass against a manager whose action is called something
                    // else -- and a custom header action is exactly the case that
                    // was broken: it carries no record, so the row-level guards never
                    // see it, and Filament's isReadOnly() hides only its own
                    // CreateAction.
                    $names = collect($mount()->instance()->getTable()->getHeaderActions())
                        ->map(fn ($action): string => $action->getName())
                        ->all();

                    expect($names)->not->toBeEmpty(
                        "{$manager} registers no header actions; drop it from this check or give it one",
                    );

                    foreach ($names as $name) {
                        $mount()
                            ->call('mountAction', $name, [], ['table' => true])
                            ->assertSet('mountedActions', []);
                    }
                })->with($rows);
            });
        });

        describe('the traits every surface needs', function () use ($contract, $when) {
            $when($contract->editPages(), function (array $rows) use ($contract) {
                it('makes the model behind every record page lockable', function (string $page) use ($contract) {
                    expect(class_uses_recursive($page::getResource()::getModel()))
                        ->toContain(\F4nu\Fllock\Models\Concerns\HasRecordLock::class);
                })->with($rows);
            });

            $when($contract->editPages(), function (array $rows) use ($contract) {
                it('guards every record page', function (string $page) {
                    expect(class_uses_recursive($page))
                        ->toContain(\F4nu\Fllock\Filament\Concerns\LocksRecordWhileEditing::class);
                })->with($rows);
            });

            $when($contract->listPages(), function (array $rows) use ($contract) {
                it('guards every list page', function (string $page) {
                    expect(class_uses_recursive($page))
                        ->toContain(\F4nu\Fllock\Filament\Concerns\LocksRecordsEditedInModals::class);
                })->with($rows);
            });

            $when($contract->customPages(), function (array $rows) use ($contract) {
                it('guards every custom page', function (string $page) {
                    expect(class_uses_recursive($page))
                        ->toContain(\F4nu\Fllock\Filament\Concerns\LocksPageWhileEditing::class);
                })->with($rows);
            });

            $when($contract->recordPages(), function (array $rows) use ($contract) {
                it('guards every record page that can write', function (string $page) use ($contract) {
                    [$first] = $contract->makeEditors();
                    $record = $contract->makeRecord($page::getResource()::getModel());

                    $contract->actingAs($first);

                    // Ask the rendered page what it can be told to do, rather
                    // than guessing from what it extends or looking in one
                    // place. Actions live in header actions, in table rows, and
                    // inside schema components -- the donation reader keeps
                    // four of them in an infolist section, which is why looking
                    // only at header actions found nothing and called the page
                    // safe.
                    $writes = collect()
                        ->pipe(function () use ($page, $record) {
                            preg_match_all(
                                "/mountAction\\(&#039;([a-zA-Z0-9_]+)&#039;|mountAction\\('([a-zA-Z0-9_]+)'/",
                                Livewire::test($page, ['record' => $record->getRouteKey()])->html(),
                                $matches,
                            );

                            return collect($matches[1])
                                ->merge($matches[2])
                                ->filter()
                                ->unique();
                        })
                        ->reject(fn (string $name): bool => $name === 'fllockForceUnlock'
                            || in_array($name, config('fllock.permitted_actions', []), true));

                    if ($writes->isEmpty()) {
                        expect(true)->toBeTrue("{$page} exposes no writes; nothing to lock");

                        return;
                    }

                    $traits = class_uses_recursive($page);

                    expect(
                        in_array(\F4nu\Fllock\Filament\Concerns\LocksRecordOnPage::class, $traits, true)
                        || in_array(\F4nu\Fllock\Filament\Concerns\LocksRecordWhileEditing::class, $traits, true)
                    )->toBeTrue(
                        "{$page} offers " . $writes->implode(', ') . ' but takes no lock'
                    );
                })->with($rows);
            });

            $when($contract->relationManagers(), function (array $rows) use ($contract) {
                it('guards every relation manager', function (string $manager) {
                    expect(class_uses_recursive($manager))
                        ->toContain(\F4nu\Fllock\Filament\Concerns\LocksRecordsEditedInModals::class)
                        ->toContain(\F4nu\Fllock\Filament\Concerns\ReadOnlyWhenOwnerRecordIsLocked::class);
                })->with($rows);
            });
        });
    }

    // ------------------------------------------------------------------ helpers

    /**
     * The model a relation manager's owner record is, found through the
     * resource whose namespace the manager sits in -- which is the only place
     * a relation manager names what it belongs to.
     */
    public function ownerModelOf(string $manager): string {
        return $this->resourceOf($manager)::getModel();
    }

    public function pageClassFor(string $manager): string {
        $pages = $this->resourceOf($manager)::getPages();

        return ($pages['edit'] ?? $pages['view'] ?? $pages['index'])->getPage();
    }

    /**
     * The resource a relation manager belongs to.
     *
     * Found by asking every resource what it declares, not by chopping the
     * manager's namespace: where a manager sits relative to its resource is a
     * layout choice, and stripping `\RelationManagers\` produces a class name
     * that does not exist as soon as the layout differs.
     */
    protected function resourceOf(string $manager): string {
        foreach ($this->classesExtending(Resource::class) as [$resource]) {
            if (in_array($manager, $resource::getRelations(), true)) {
                return $resource;
            }
        }

        throw new \RuntimeException("No resource declares {$manager} in getRelations().");
    }

    /** @return array{0: Model, 1: Model} */
    public function makeEditors(): array {
        return ($this->editors)();
    }

    public function makeRecord(string $model): Model {
        return ($this->record)($model);
    }

    /** Pretend a lock has been sitting untouched for a while. */
    public function age(Model $record, int $seconds): void {
        $record->fresh()->recordLock?->update([
            'updated_at' => now()->subSeconds($seconds),
        ]);
    }

    public function actingAs(Model $user): void {
        test()->actingAs($user);
    }

    /**
     * A column name to poke the inline-write endpoint with.
     *
     * Any attribute of the record will do -- the guard has to refuse before it
     * ever looks at which column was named, and picking a real one keeps the
     * test honest if the guard is removed.
     */
    public function probeColumn(Model $record): string {
        return array_key_first($record->getAttributes()) ?? 'id';
    }

    public function openEditPage(string $page, Model $record, Model $as) {
        $this->actingAs($as);

        // The route key, not the primary key: plenty of records are addressed
        // by uuid.
        return Livewire::test($page, ['record' => $record->getRouteKey()])
            ->dispatch('fllock::init');
    }
}
