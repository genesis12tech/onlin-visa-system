<?php

namespace App\Filament\Resources\VisaApplications\Pages;

use App\Filament\Resources\VisaApplications\VisaApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVisaApplications extends ListRecords
{
    protected static string $resource = VisaApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
