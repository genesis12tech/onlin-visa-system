<?php

namespace App\Filament\Resources\VisaApplications\Pages;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Filament\Resources\VisaApplications\VisaApplicationResource;
use App\Jobs\ExportApplicationsJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListVisaApplications extends ListRecords
{
    protected static string $resource = VisaApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): void {
                    ExportApplicationsJob::dispatch([], auth()->id());
                    Notification::make()
                        ->success()
                        ->title('Export queued. Download it from Settings → Exports when ready.')
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Export Applications to CSV')
                ->modalDescription('All submitted applications will be exported. Check Settings → Exports to download when ready.')
                ->modalSubmitActionLabel('Start Export'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),

            'submitted' => Tab::make('Submitted')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ApplicationStatus::Submitted->value)),

            'under_review' => Tab::make('In review')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ApplicationStatus::UnderReview->value)),

            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ApplicationStatus::Approved->value)),

            'rejected' => Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ApplicationStatus::Rejected->value)),
        ];
    }
}
