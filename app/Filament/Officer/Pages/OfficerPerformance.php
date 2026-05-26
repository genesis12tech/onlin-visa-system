<?php

namespace App\Filament\Officer\Pages;

use App\Domain\Reporting\Models\OfficerPerformanceMetrics;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use UnitEnum;

class OfficerPerformance extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reporting';

    protected static ?string $navigationLabel = 'My Performance';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.officer.pages.officer-performance';

    // TODO(M7): This page reads from officer_performance_metrics, populated nightly by GenerateDailyMetricsJob.
    // The table will be empty until that job has run at least once.
    public function getSummary(): array
    {
        $metrics = OfficerPerformanceMetrics::where('officer_id', auth()->id())
            ->where('date', '>=', Carbon::now()->subDays(30))
            ->get();

        return [
            'reviewed' => $metrics->sum('reviewed_count'),
            'approved' => $metrics->sum('approved_count'),
            'rejected' => $metrics->sum('rejected_count'),
            'info_requested' => $metrics->sum('info_requested_count'),
            'avg_hours' => $metrics->avg('avg_review_hours'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OfficerPerformanceMetrics::where('officer_id', auth()->id())
                    ->where('date', '>=', Carbon::now()->subDays(30))
                    ->latest('date')
            )
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('reviewed_count')
                    ->label('Reviewed')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('approved_count')
                    ->label('Approved')
                    ->numeric()
                    ->color('success'),

                TextColumn::make('rejected_count')
                    ->label('Rejected')
                    ->numeric()
                    ->color('danger'),

                TextColumn::make('info_requested_count')
                    ->label('Info Requested')
                    ->numeric(),

                TextColumn::make('avg_review_hours')
                    ->label('Avg Hours')
                    ->formatStateUsing(fn ($state): string => $state !== null ? round((float) $state, 1).'h' : '—'),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('date', 'desc')
            ->paginated([30, 60]);
    }
}
