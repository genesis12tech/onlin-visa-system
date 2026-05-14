<?php

namespace App\Filament\Resources\VisaApplications\Pages;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Filament\Resources\VisaApplications\VisaApplicationResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListVisaApplications extends ListRecords
{
    protected static string $resource = VisaApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
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
