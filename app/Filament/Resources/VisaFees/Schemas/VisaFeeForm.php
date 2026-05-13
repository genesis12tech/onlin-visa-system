<?php

namespace App\Filament\Resources\VisaFees\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VisaFeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('visa_type_id')
                    ->relationship('visaType', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->suffix('cents')
                    ->helperText('Enter in cents. 5000 = $50.00'),
                Select::make('currency')
                    ->options(['USD' => 'USD', 'GBP' => 'GBP', 'EUR' => 'EUR'])
                    ->default('USD')
                    ->required(),
                Select::make('applicant_type')
                    ->options([
                        'all' => 'All Applicants',
                        'adult' => 'Adult',
                        'child' => 'Child',
                        'senior' => 'Senior',
                    ])
                    ->default('all')
                    ->required(),
                DatePicker::make('effective_from')
                    ->required(),
                DatePicker::make('effective_to')
                    ->after('effective_from'),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
