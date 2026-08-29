<?php

namespace F4nu\Fllock\Filament\Resources\Pages;

use F4nu\Fllock\Filament\Resources\RecordLockResource;
use Filament\Resources\Pages\ManageRecords;

class ManageRecordLocks extends ManageRecords {
    protected static string $resource = RecordLockResource::class;

    /** @return array<mixed> */
    protected function getHeaderActions(): array {
        return [];
    }
}
