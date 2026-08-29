<?php

namespace F4nu\Fllock\Testing;

use Closure;
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

    /** @return array<string, array{0: class-string}> */
    public function relationManagers(): array {
        return $this->classesExtending(RelationManager::class);
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

        describe('record locking', function () use ($contract) {
            it('locks a record while its edit page is open', function (string $page) use ($contract) {
                [$first] = $contract->makeEditors();
                $record = $contract->makeRecord($page::getResource()::getModel());

                $contract->openEditPage($page, $record, $first);

                expect($record->fresh()->isLocked())->toBeTrue();
            })->with($contract->editPages());

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
            })->with($contract->editPages());

            it('goes read-only for a second editor, with no header actions left', function (string $page) use ($contract) {
                [$first, $second] = $contract->makeEditors();
                $record = $contract->makeRecord($page::getResource()::getModel());

                $contract->openEditPage($page, $record, $first);

                $component = $contract->openEditPage($page, $record, $second)
                    ->assertSet('isReadOnly', true);

                foreach ($component->instance()->getCachedHeaderActions() as $action) {
                    expect($action->getName())->toBe('fllockForceUnlock');
                }
            })->with($contract->editPages());

            it('stays disabled when the schema is rebuilt', function (string $page) use ($contract) {
                [$first, $second] = $contract->makeEditors();
                $record = $contract->makeRecord($page::getResource()::getModel());

                $contract->openEditPage($page, $record, $first);

                $component = $contract->openEditPage($page, $record, $second);

                // Switching relation manager tabs rebuilds the schema. Disable
                // the form imperatively and every field comes back to life here.
                $component->set('activeRelationManager', 0)->assertSet('isReadOnly', true);

                expect($component->instance()->form->isDisabled())->toBeTrue();
            })->with($contract->editPages());

            it('refuses a save from a second editor', function (string $page) use ($contract) {
                [$first, $second] = $contract->makeEditors();
                $record = $contract->makeRecord($page::getResource()::getModel());

                $contract->openEditPage($page, $record, $first);

                $before = $record->fresh()->updated_at;
                $contract->openEditPage($page, $record, $second)->call('save');

                expect($record->fresh()->updated_at?->toString())->toBe($before?->toString());
            })->with($contract->editPages());

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
            })->with($contract->listPages());

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
            })->with($contract->listPages());

            it('refuses reordering that touches a locked row', function (string $page) use ($contract) {
                [$first, $second] = $contract->makeEditors();
                $record = $contract->makeRecord($page::getResource()::getModel());

                $contract->actingAs($first);
                $record->lock();

                $before = $record->fresh()->updated_at?->toString();

                $contract->actingAs($second);
                Livewire::test($page)->call('reorderTable', [(string) $record->getKey()]);

                expect($record->fresh()->updated_at?->toString())->toBe($before);
            })->with($contract->listPages());
        });

        describe('the traits every surface needs', function () use ($contract) {
            it('makes the model behind every record page lockable', function (string $page) use ($contract) {
                expect(class_uses_recursive($page::getResource()::getModel()))
                    ->toContain(\F4nu\Fllock\Models\Concerns\HasRecordLock::class);
            })->with($contract->editPages());

            it('guards every record page', function (string $page) {
                expect(class_uses_recursive($page))
                    ->toContain(\F4nu\Fllock\Filament\Concerns\LocksRecordWhileEditing::class);
            })->with($contract->editPages());

            it('guards every list page', function (string $page) {
                expect(class_uses_recursive($page))
                    ->toContain(\F4nu\Fllock\Filament\Concerns\LocksRecordsEditedInModals::class);
            })->with($contract->listPages());

            it('guards every relation manager', function (string $manager) {
                expect(class_uses_recursive($manager))
                    ->toContain(\F4nu\Fllock\Filament\Concerns\LocksRecordsEditedInModals::class)
                    ->toContain(\F4nu\Fllock\Filament\Concerns\ReadOnlyWhenOwnerRecordIsLocked::class);
            })->with($contract->relationManagers());
        });
    }

    // ------------------------------------------------------------------ helpers

    /** @return array{0: Model, 1: Model} */
    public function makeEditors(): array {
        return ($this->editors)();
    }

    public function makeRecord(string $model): Model {
        return ($this->record)($model);
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
