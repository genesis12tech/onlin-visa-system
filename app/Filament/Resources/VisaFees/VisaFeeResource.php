<?php

namespace App\Filament\Resources\VisaFees;

use App\Domain\Payments\Models\VisaFee;
use App\Filament\Resources\VisaFees\Pages\CreateVisaFee;
use App\Filament\Resources\VisaFees\Pages\EditVisaFee;
use App\Filament\Resources\VisaFees\Pages\ListVisaFees;
use App\Filament\Resources\VisaFees\Schemas\VisaFeeForm;
use App\Filament\Resources\VisaFees\Tables\VisaFeesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VisaFeeResource extends Resource
{
    protected static ?string $model = VisaFee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return VisaFeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VisaFeesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisaFees::route('/'),
            'create' => CreateVisaFee::route('/create'),
            'edit' => EditVisaFee::route('/{record}/edit'),
        ];
    }
}
