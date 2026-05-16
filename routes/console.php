<?php

use App\Domain\Reporting\Jobs\AggregateDailyApplicationMetrics;
use App\Domain\Reporting\Jobs\AggregateDailyPaymentMetrics;
use App\Domain\Reporting\Jobs\AggregateDocumentRejectionMetrics;
use App\Domain\Reporting\Jobs\AggregateOfficerPerformanceMetrics;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $yesterday = now()->subDay()->toDateString();
    AggregateDailyApplicationMetrics::dispatch($yesterday);
    AggregateDailyPaymentMetrics::dispatch($yesterday);
    AggregateOfficerPerformanceMetrics::dispatch($yesterday);
    AggregateDocumentRejectionMetrics::dispatch($yesterday);
})->dailyAt('00:30')->name('aggregate-daily-metrics')->withoutOverlapping();
