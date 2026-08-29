<?php

namespace F4nu\Fllock\Tests\Fixtures\Filament\Resources;

use F4nu\Fllock\Filament\Tables\Columns\RecordLockColumn;
use F4nu\Fllock\Tests\Fixtures\Filament\Resources\Pages\EditPost;
use F4nu\Fllock\Tests\Fixtures\Filament\Resources\Pages\ListPosts;
use F4nu\Fllock\Tests\Fixtures\Post;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/**
 * A resource shaped like the ones that actually broke: an inline toggle column,
 * a reorderable table, a bulk delete, and a custom row action that writes.
 */
class PostResource extends Resource {
    protected static ?string $model = Post::class;

    public static function form(Schema $schema): Schema {
        return $schema->components([
            TextInput::make('title')->required(),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
            ->columns([
                RecordLockColumn::make(),
                TextColumn::make('title'),
                ToggleColumn::make('is_published'),
            ])
            ->reorderable('position')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                // Not an edit and not a delete: the kind of action a denylist of
                // built-in names walks straight past.
                Action::make('publish')
                    ->action(fn (Post $record) => $record->update(['is_published' => true])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array {
        return [
            'index' => ListPosts::route('/'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array {
        return [RelationManagers\CommentsRelationManager::class];
    }
}
