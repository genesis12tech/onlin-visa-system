<?php

namespace App\Filament\Resources\VisaTypes\RelationManagers;

use App\Domain\Documents\Models\DocumentType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentRequirementsRelationManager extends RelationManager
{
    protected static string $relationship = 'documentRequirements';

    protected static ?string $title = 'Required Documents';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('document_type_id')
                    ->label('Document Type')
                    ->options(DocumentType::where('is_active', true)->pluck('name', 'ulid'))
                    ->required()
                    ->searchable(),
                TextInput::make('display_order')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_required')
                    ->label('Required')
                    ->default(true),
                Textarea::make('notes')
                    ->label('Instructions for Applicant')
                    ->rows(2)
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('document_type_id')
            ->columns([
                TextColumn::make('documentType.name')
                    ->label('Document Type')
                    ->sortable(),
                TextColumn::make('display_order')
                    ->label('Order')
                    ->sortable(),
                IconColumn::make('is_required')
                    ->label('Required')
                    ->boolean(),
                TextColumn::make('notes')
                    ->limit(50)
                    ->placeholder('—'),
            ])
            ->defaultSort('display_order')
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
