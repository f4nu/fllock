<?php

namespace F4nu\Fllock\Tests\Fixtures\Filament\Resources\Pages;

use F4nu\Fllock\Filament\Concerns\EagerLoadsRecordLocks;
use F4nu\Fllock\Filament\Concerns\LocksRecordsEditedInModals;
use F4nu\Fllock\Tests\Fixtures\Filament\Resources\PostResource;
use Filament\Resources\Pages\ListRecords;

class ListPosts extends ListRecords {
    use EagerLoadsRecordLocks;
    use LocksRecordsEditedInModals;

    protected static string $resource = PostResource::class;
}
