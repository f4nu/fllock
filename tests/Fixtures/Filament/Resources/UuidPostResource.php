<?php

namespace F4nu\Fllock\Tests\Fixtures\Filament\Resources;

use F4nu\Fllock\Tests\Fixtures\Filament\Resources\Pages\ListUuidPosts;
use F4nu\Fllock\Tests\Fixtures\UuidPost;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * A table written only through bulk actions, like a reconciliation list an API
 * fills: no row is ever edited, so there is no row `edit` action to mount.
 */
class UuidPostResource extends Resource {
    protected static ?string $model = UuidPost::class;

    public static function table(Table $table): Table {
        return $table
            ->columns([
                TextColumn::make('title'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array {
        return [
            'index' => ListUuidPosts::route('/'),
        ];
    }
}
