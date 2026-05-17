<?php

namespace App\Filament\Widgets;

use App\Domain\Reporting\Models\OfficerPerformanceMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Support\Carbon;

class OfficerPerformanceWidget extends BaseTableWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Officer performance — current month';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OfficerPerformanceMetrics::with('officer')
                    ->where('date', '>=', Carbon::now()->startOfMonth())
                    ->selectRaw('MIN(id) as id, officer_id, SUM(reviewed_count) as reviewed_count, SUM(approved_count) as approved_count, SUM(rejected_count) as rejected_count, AVG(avg_review_hours) as avg_review_hours')
                    ->groupBy('officer_id')
                    ->orderByDesc('reviewed_count'),
            )
            ->columns([
                TextColumn::make('officer.name')
                    ->label('Officer')
                    ->default('—'),

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

                TextColumn::make('avg_review_hours')
                    ->label('Avg Hrs/Day')
                    ->description('Average review hours per working day')
                    ->formatStateUsing(fn ($state): string => $state !== null ? round((float) $state, 1).'h' : '—'),
            ])
            ->paginated(false);
    }
}
