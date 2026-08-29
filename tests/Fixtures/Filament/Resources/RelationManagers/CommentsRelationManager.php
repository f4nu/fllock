<?php

namespace F4nu\Fllock\Tests\Fixtures\Filament\Resources\RelationManagers;

use F4nu\Fllock\Filament\Concerns\LocksRecordsEditedInModals;
use F4nu\Fllock\Filament\Concerns\ReadOnlyWhenOwnerRecordIsLocked;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager {
    use LocksRecordsEditedInModals;
    use ReadOnlyWhenOwnerRecordIsLocked;

    protected static string $relationship = 'comments';

    public function form(Schema $schema): Schema {
        return $schema->components([TextInput::make('body')->required()]);
    }

    public function table(Table $table): Table {
        return $table
            ->columns([
                TextColumn::make('body'),
                // The shape of the bug: an inline toggle on a related row, which
                // no action guard and no isReadOnly() check ever sees.
                ToggleColumn::make('approved'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
