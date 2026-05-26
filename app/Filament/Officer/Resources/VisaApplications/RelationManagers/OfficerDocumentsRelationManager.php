<?php

namespace App\Filament\Officer\Resources\VisaApplications\RelationManagers;

use App\Domain\Documents\Actions\AcceptDocument;
use App\Domain\Documents\Actions\RejectDocument;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

class OfficerDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ulid')
            ->columns([
                TextColumn::make('documentType.name')
                    ->label('Document Type')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (DocumentStatus $state): string => match ($state) {
                        DocumentStatus::Accepted => 'success',
                        DocumentStatus::Rejected, DocumentStatus::Infected => 'danger',
                        DocumentStatus::Uploaded, DocumentStatus::UnderReview => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('currentVersion.original_filename')
                    ->label('Filename')
                    ->placeholder('Not uploaded'),

                TextColumn::make('currentVersion.scan_status')
                    ->label('Scan')
                    ->badge()
                    ->color(fn (?ScanStatus $state): string => match ($state) {
                        ScanStatus::Clean => 'success',
                        ScanStatus::Infected => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('—'),

                TextColumn::make('reviewer.name')
                    ->label('Reviewed By')
                    ->placeholder('—'),

                TextColumn::make('reviewed_at')
                    ->label('Reviewed At')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->filters([])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading('Document Preview')
                    ->modalWidth('4xl')
                    ->modalContent(function (ApplicationDocument $record): HtmlString {
                        $version = $record->currentVersion;

                        if (! $version || $version->scan_status !== ScanStatus::Clean) {
                            return new HtmlString('<p class="p-4 text-sm text-gray-500">Document not available for preview.</p>');
                        }

                        $previewUrl = URL::temporarySignedRoute(
                            'officer.documents.preview',
                            now()->addMinutes(15),
                            ['version' => $version->ulid],
                        );

                        if (str_starts_with($version->mime_type ?? '', 'image/')) {
                            return new HtmlString(
                                '<div class="p-4"><img src="'.e($previewUrl).'" class="max-w-full h-auto mx-auto" alt="Document preview" /></div>'
                            );
                        }

                        return new HtmlString(
                            '<iframe src="'.e($previewUrl).'" class="w-full border-0" style="height:75vh;" title="Document preview"></iframe>'
                        );
                    })
                    ->visible(fn (ApplicationDocument $record): bool => $record->currentVersion !== null)
                    ->authorize(fn (ApplicationDocument $record): bool => auth()->user()?->can('view', $record) ?? false),

                Action::make('accept')
                    ->label('Accept')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (ApplicationDocument $record) => (new AcceptDocument)->execute($record, auth()->user()))
                    ->visible(fn (ApplicationDocument $record): bool => in_array($record->status, [DocumentStatus::Uploaded, DocumentStatus::UnderReview])
                        && $record->currentVersion?->scan_status === ScanStatus::Clean
                    )
                    ->authorize(fn (ApplicationDocument $record): bool => auth()->user()?->can('accept', $record) ?? false),

                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(fn (ApplicationDocument $record, array $data) => (new RejectDocument)->execute($record, auth()->user(), $data['rejection_reason']))
                    ->visible(fn (ApplicationDocument $record): bool => in_array($record->status, [DocumentStatus::Uploaded, DocumentStatus::UnderReview, DocumentStatus::Accepted]))
                    ->authorize(fn (ApplicationDocument $record): bool => auth()->user()?->can('reject', $record) ?? false),
            ])
            ->headerActions([])
            ->toolbarActions([]);
    }
}
