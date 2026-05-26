<?php

namespace App\Filament\Officer\Resources\VisaApplications\RelationManagers;

use App\Domain\Applications\Actions\AddReviewNote;
use App\Domain\Applications\Models\ApplicationNote;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OfficerNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Officer Notes';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')
                ->label('Note')
                ->required()
                ->rows(4)
                ->columnSpanFull(),

            Checkbox::make('is_visible_to_applicant')
                ->label('Visible to applicant'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('author.name')
                    ->label('Author')
                    ->sortable(),

                TextColumn::make('body')
                    ->label('Note')
                    ->limit(120)
                    ->wrap(),

                IconColumn::make('is_visible_to_applicant')
                    ->label('Visible to applicant')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'senior_officer', 'case_officer']) ?? false)
                    ->using(function (array $data): ApplicationNote {
                        return (new AddReviewNote)->execute(
                            $this->getOwnerRecord(),
                            auth()->user(),
                            $data['body'],
                            $data['is_visible_to_applicant'] ?? false,
                        );
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
