<?php

namespace Tests\Unit;

use App\Domain\Reporting\Models\DailyPaymentMetrics;
use App\Domain\Reporting\Models\DocumentRejectionMetrics;
use App\Domain\Reporting\Models\OfficerPerformanceMetrics;
use PHPUnit\Framework\TestCase;

class ReportingModelsTest extends TestCase
{
    public function test_daily_payment_metrics_casts_integers(): void
    {
        $model = new DailyPaymentMetrics([
            'total_collected' => '1000',
            'total_refunded' => '50',
            'succeeded_count' => '5',
            'failed_count' => '1',
        ]);

        $this->assertIsInt($model->total_collected);
        $this->assertIsInt($model->total_refunded);
        $this->assertIsInt($model->succeeded_count);
        $this->assertIsInt($model->failed_count);
    }

    public function test_officer_performance_metrics_casts_correctly(): void
    {
        $model = new OfficerPerformanceMetrics([
            'reviewed_count' => '10',
            'approved_count' => '7',
            'rejected_count' => '3',
            'info_requested_count' => '2',
            'avg_review_hours' => '4.5',
        ]);

        $this->assertIsInt($model->reviewed_count);
        $this->assertIsInt($model->approved_count);
        $this->assertIsInt($model->rejected_count);
        $this->assertIsInt($model->info_requested_count);
        $this->assertIsFloat($model->avg_review_hours);
    }

    public function test_document_rejection_metrics_casts_top_reasons_to_array(): void
    {
        $model = new DocumentRejectionMetrics([
            'rejection_count' => '3',
            'top_reasons' => [['reason' => 'blurry', 'count' => 2]],
        ]);

        $this->assertIsInt($model->rejection_count);
        $this->assertIsArray($model->top_reasons);
        $this->assertSame('blurry', $model->top_reasons[0]['reason']);
    }
}
