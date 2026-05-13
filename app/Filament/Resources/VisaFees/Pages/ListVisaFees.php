<?php

namespace App\Filament\Resources\VisaFees\Pages;

use App\Filament\Resources\VisaFees\VisaFeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVisaFees extends ListRecords
{
    protected static string $resource = VisaFeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
