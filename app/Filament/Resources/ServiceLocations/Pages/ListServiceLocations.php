<?php

namespace App\Filament\Resources\ServiceLocations\Pages;

use App\Filament\Resources\ServiceLocations\ServiceLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceLocations extends ListRecords
{
    protected static string $resource = ServiceLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
