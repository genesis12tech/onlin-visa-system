<?php

namespace App\Filament\Resources\VisaApplications\Pages;

use App\Filament\Resources\VisaApplications\VisaApplicationResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;

class ListVisaApplications extends ListRecords
{
    protected static string $resource = VisaApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'submitted' => Tab::make('Submitted'),
            'under_review' => Tab::make('In review'),
            'approved' => Tab::make('Approved'),
            'rejected' => Tab::make('Rejected'),
        ];
    }
}
