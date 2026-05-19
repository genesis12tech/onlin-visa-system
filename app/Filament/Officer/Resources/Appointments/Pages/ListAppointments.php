<?php

namespace App\Filament\Officer\Resources\Appointments\Pages;

use App\Filament\Officer\Resources\Appointments\AppointmentResource;
use Filament\Resources\Pages\ListRecords;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;
}
