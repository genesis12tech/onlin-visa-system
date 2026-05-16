<?php

namespace App\Filament\Resources\ApplicationExports\Pages;

use App\Filament\Resources\ApplicationExports\ApplicationExportResource;
use Filament\Resources\Pages\ListRecords;

class ListApplicationExports extends ListRecords
{
    protected static string $resource = ApplicationExportResource::class;
}
