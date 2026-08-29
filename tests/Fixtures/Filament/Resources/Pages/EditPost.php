<?php

namespace F4nu\Fllock\Tests\Fixtures\Filament\Resources\Pages;

use F4nu\Fllock\Filament\Concerns\LocksRecordWhileEditing;
use F4nu\Fllock\Tests\Fixtures\Filament\Resources\PostResource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditPost extends EditRecord {
    use LocksRecordWhileEditing;

    protected static string $resource = PostResource::class;

    /**
     * Overrides form() on purpose. A class method beats a trait method, so a
     * page shaped like this is how the read-only guard silently disappeared --
     * the render hook in the trait is what has to catch it.
     */
    public function form(Schema $schema): Schema {
        return $schema->components([
            TextInput::make('title')->required(),
        ]);
    }

    /** @return array<mixed> */
    protected function getHeaderActions(): array {
        return array_merge(parent::getHeaderActions(), [
            DeleteAction::make(),
        ]);
    }
}
