<?php

namespace App\Filament\Resources\VisaFees\Pages;

use App\Filament\Resources\VisaFees\VisaFeeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVisaFee extends EditRecord
{
    protected static string $resource = VisaFeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
