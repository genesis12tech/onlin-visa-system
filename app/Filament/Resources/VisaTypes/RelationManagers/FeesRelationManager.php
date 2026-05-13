<?php

namespace App\Filament\Resources\VisaTypes\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FeesRelationManager extends RelationManager
{
    protected static string $relationship = 'fees';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                DatePicker::make('effective_to'),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('amount')
                    ->formatStateUsing(fn (int $state, $record): string => number_format($state / 100, 2).' '.$record->currency),
                TextColumn::make('applicant_type')
                    ->badge(),
                TextColumn::make('effective_from')
                    ->date(),
                TextColumn::make('effective_to')
                    ->date()
                    ->placeholder('Open-ended'),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
