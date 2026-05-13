<?php

namespace App\Filament\Resources\VisaTypes\Pages;

use App\Filament\Resources\VisaTypes\VisaTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVisaTypes extends ListRecords
{
    protected static string $resource = VisaTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
