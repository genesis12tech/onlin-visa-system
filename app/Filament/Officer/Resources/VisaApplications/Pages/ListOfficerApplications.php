<?php

namespace App\Filament\Officer\Resources\VisaApplications\Pages;

use App\Filament\Officer\Resources\VisaApplications\OfficerVisaApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListOfficerApplications extends ListRecords
{
    protected static string $resource = OfficerVisaApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
