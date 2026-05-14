<?php

namespace App\Filament\Widgets;

use App\Domain\Applications\Models\VisaApplication;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ByVisaTypeWidget extends ChartWidget
{
    protected string $view = 'filament.widgets.by-visa-type-widget';

    protected static ?int $sort = 3;

    protected ?string $heading = 'By visa type';

    protected ?string $description = 'Current month';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $rows = VisaApplication::select('visa_types.name', DB::raw('COUNT(*) as count'))
            ->join('visa_types', 'visa_applications.visa_type_id', '=', 'visa_types.ulid')
            ->where('visa_applications.submitted_at', '>=', Carbon::now()->startOfMonth())
            ->groupBy('visa_types.ulid', 'visa_types.name')
            ->orderByDesc('count')
            ->get();

        $colors = [
            'rgba(99, 102, 241, 0.8)',
            'rgba(16, 185, 129, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(239, 68, 68, 0.8)',
            'rgba(139, 92, 246, 0.8)',
            'rgba(59, 130, 246, 0.8)',
        ];

        return [
            'labels' => $rows->pluck('name')->toArray(),
            'datasets' => [
                [
                    'label' => 'Applications',
                    'data' => $rows->pluck('count')->toArray(),
                    'backgroundColor' => array_slice($colors, 0, $rows->count()),
                    'borderWidth' => 2,
                    'borderColor' => '#1E293B',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'cutout' => '65%',
        ];
    }
}
