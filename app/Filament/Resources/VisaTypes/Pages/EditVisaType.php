<?php

namespace App\Filament\Resources\VisaTypes\Pages;

use App\Filament\Resources\VisaTypes\VisaTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVisaType extends EditRecord
{
    protected static string $resource = VisaTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
