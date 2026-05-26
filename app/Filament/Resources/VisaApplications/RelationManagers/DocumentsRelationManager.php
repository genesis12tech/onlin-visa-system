<?php

namespace App\Filament\Resources\VisaApplications\RelationManagers;

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

class DocumentsRelationManager extends RelationManager
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
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->filters([])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (ApplicationDocument $record): string => $record->currentVersion
                            ? URL::temporarySignedRoute(
                                'documents.download',
                                now()->addMinutes(15),
                                ['version' => $record->currentVersion->ulid],
                            )
                            : '#'
                    )
                    ->openUrlInNewTab()
                    ->visible(fn (ApplicationDocument $record): bool => $record->currentVersion !== null
                        && $record->currentVersion->scan_status === ScanStatus::Clean
                    )
                    ->authorize(fn (ApplicationDocument $record): bool => auth()->user()?->can('view', $record) ?? false
                    ),

                Action::make('accept')
                    ->label('Accept')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (ApplicationDocument $record) => (new AcceptDocument)->execute($record, auth()->user())
                    )
                    ->visible(fn (ApplicationDocument $record): bool => in_array($record->status, [DocumentStatus::Uploaded, DocumentStatus::UnderReview])
                        && $record->currentVersion?->scan_status === ScanStatus::Clean
                    )
                    ->authorize(fn (ApplicationDocument $record): bool => auth()->user()?->can('accept', $record) ?? false
                    ),

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
                    ->action(fn (ApplicationDocument $record, array $data) => (new RejectDocument)->execute($record, auth()->user(), $data['rejection_reason'])
                    )
                    ->visible(fn (ApplicationDocument $record): bool => in_array($record->status, [DocumentStatus::Uploaded, DocumentStatus::UnderReview, DocumentStatus::Accepted])
                    )
                    ->authorize(fn (ApplicationDocument $record): bool => auth()->user()?->can('reject', $record) ?? false
                    ),

                Action::make('mark_scan_clean')
                    ->label('Mark Scan Clean')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (ApplicationDocument $record) => $record->currentVersion?->update([
                        'scan_status' => ScanStatus::Clean,
                        'scan_completed_at' => now(),
                    ]))
                    ->visible(fn (ApplicationDocument $record): bool => $record->currentVersion !== null
                        && $record->currentVersion->scan_status === ScanStatus::Pending
                    )
                    ->authorize(fn (ApplicationDocument $record): bool => auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false
                    ),
            ])
            ->headerActions([])
            ->toolbarActions([]);
    }
}
