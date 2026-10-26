<?php

namespace F4nu\Fllock\Tests\Fixtures\Filament\Resources\Pages;

use F4nu\Fllock\Filament\Concerns\EagerLoadsRecordLocks;
use F4nu\Fllock\Filament\Concerns\LocksRecordsEditedInModals;
use F4nu\Fllock\Tests\Fixtures\Filament\Resources\UuidPostResource;
use Filament\Resources\Pages\ListRecords;

class ListUuidPosts extends ListRecords {
    use EagerLoadsRecordLocks;
    use LocksRecordsEditedInModals;

    protected static string $resource = UuidPostResource::class;
}
