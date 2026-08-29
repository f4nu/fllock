<?php

namespace F4nu\Fllock\Filament\Resources;

use F4nu\Fllock\Filament\Resources\Pages\ManageRecordLocks;
use F4nu\Fllock\Models\RecordLock;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * What is locked, by whom, and since when -- plus the way to clear one.
 *
 * Unlocking here is the same forced unlock the read-only banner offers, so it
 * sits behind the same gate: it throws away whatever the current editor has
 * typed and not saved.
 */
class RecordLockResource extends Resource {
    protected static ?string $model = RecordLock::class;

    public static function getModelLabel(): string {
        return __('fllock::fllock.manager.label');
    }

    public static function getPluralModelLabel(): string {
        return __('fllock::fllock.manager.plural_label');
    }

    public static function getNavigationIcon(): ?string {
        return config('fllock.manager.navigation_icon', 'heroicon-o-lock-closed');
    }

    public static function getNavigationGroup(): ?string {
        return config('fllock.manager.navigation_group');
    }

    public static function getNavigationSort(): ?int {
        return config('fllock.manager.navigation_sort');
    }

    public static function canViewAny(): bool {
        $gate = config('fllock.manager.gate');

        return $gate === null || Gate::allows($gate);
    }

    public static function table(Table $table): Table {
        return $table
            ->columns([
                TextColumn::make('lockable_type')
                    ->label(__('fllock::fllock.manager.type'))
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->sortable(),
                TextColumn::make('lockable_id')
                    ->label(__('fllock::fllock.manager.record')),
                TextColumn::make('user.' . config('fllock.owner_name_attribute', 'name'))
                    ->label(__('fllock::fllock.manager.owner'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('fllock::fllock.manager.since'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('fllock::fllock.manager.renewed'))
                    ->since()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('fllock::fllock.manager.status'))
                    ->badge()
                    ->state(fn (RecordLock $record): string => $record->isExpired()
                        ? __('fllock::fllock.manager.expired')
                        : __('fllock::fllock.manager.active'))
                    ->color(fn (RecordLock $record): string => $record->isExpired() ? 'gray' : 'success'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                DeleteAction::make()
                    ->label(__('fllock::fllock.manager.unlock'))
                    ->icon('heroicon-o-lock-open')
                    ->successNotificationTitle(__('fllock::fllock.manager.unlocked')),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->label(__('fllock::fllock.manager.unlock'))
                    ->icon('heroicon-o-lock-open'),
            ]);
    }

    public static function getPages(): array {
        return [
            'index' => ManageRecordLocks::route('/'),
        ];
    }
}
