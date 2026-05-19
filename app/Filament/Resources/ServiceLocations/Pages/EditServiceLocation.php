<?php

namespace App\Filament\Resources\ServiceLocations\Pages;

use App\Filament\Resources\ServiceLocations\ServiceLocationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditServiceLocation extends EditRecord
{
    protected static string $resource = ServiceLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
