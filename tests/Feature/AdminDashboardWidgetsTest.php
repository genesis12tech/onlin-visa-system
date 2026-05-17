<?php

namespace Tests\Feature;

use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Reporting\Models\DailyApplicationMetrics;
use App\Domain\Reporting\Models\DailyPaymentMetrics;
use App\Domain\Reporting\Models\DocumentRejectionMetrics;
use App\Domain\Reporting\Models\OfficerPerformanceMetrics;
use App\Filament\Widgets\DocumentRejectionStatsWidget;
use App\Filament\Widgets\OfficerPerformanceWidget;
use App\Filament\Widgets\PaymentStatsWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminDashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Cache::flush();
    }

    public function test_stats_overview_widget_shows_submitted_total_from_daily_metrics(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $type = VisaType::factory()->create();
        DailyApplicationMetrics::create([
            'date' => today()->subDay(),
            'visa_type_id' => $type->ulid,
            'submitted_count' => 57,
            'approved_count' => 10,
            'rejected_count' => 3,
            'pending_count' => 44,
            'avg_processing_days' => 5.0,
        ]);

        Livewire::actingAs($admin)
            ->test(StatsOverviewWidget::class)
            ->assertSee('57');
    }

    public function test_stats_overview_widget_renders_with_no_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(StatsOverviewWidget::class)
            ->assertSuccessful();
    }

    public function test_stats_overview_widget_does_not_query_visa_applications_table(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $queries = [];
        \DB::listen(fn ($q) => $queries[] = $q->sql);

        $component = Livewire::actingAs($admin)
            ->test(StatsOverviewWidget::class);

        // Guard: verify the widget actually rendered and returned stats (getStats was called)
        $component->assertSee('Total Applications', escape: false);

        $tablesQueried = collect($queries)
            ->filter(fn ($sql) => str_contains(strtolower($sql), 'visa_applications'))
            ->values();

        $this->assertCount(0, $tablesQueried, 'StatsOverviewWidget must not query visa_applications; found: '.implode(', ', $tablesQueried->toArray()));
    }

    public function test_payment_stats_widget_shows_collected_this_month(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        DailyPaymentMetrics::create([
            'date' => today(),
            'currency' => 'USD',
            'total_collected' => 25000, // $250.00 in cents
            'total_refunded' => 0,
            'succeeded_count' => 5,
            'failed_count' => 1,
        ]);

        Livewire::actingAs($admin)
            ->test(PaymentStatsWidget::class)
            ->assertSee('250.00');
    }

    public function test_payment_stats_widget_renders_with_no_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(PaymentStatsWidget::class)
            ->assertSuccessful()
            ->assertSee('$0.00');
    }

    public function test_document_rejection_widget_shows_monthly_count(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $docType = DocumentType::factory()->create(['name' => 'Passport Scan']);

        DocumentRejectionMetrics::create([
            'date' => today(),
            'document_type_id' => $docType->ulid,
            'rejection_count' => 12,
            'top_reasons' => json_encode([['reason' => 'blurry', 'count' => 8]]),
        ]);

        Livewire::actingAs($admin)
            ->test(DocumentRejectionStatsWidget::class)
            ->assertSee('12');
    }

    public function test_document_rejection_widget_renders_with_no_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(DocumentRejectionStatsWidget::class)
            ->assertSuccessful();
    }

    public function test_officer_performance_widget_shows_officer_stats(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $officer = User::factory()->create(['name' => 'Jane Officer']);

        OfficerPerformanceMetrics::create([
            'date' => today(),
            'officer_id' => $officer->id,
            'reviewed_count' => 14,
            'approved_count' => 10,
            'rejected_count' => 4,
            'info_requested_count' => 2,
            'avg_review_hours' => 3.5,
        ]);

        Livewire::actingAs($admin)
            ->test(OfficerPerformanceWidget::class)
            ->assertSee('Jane Officer')
            ->assertSee('14');
    }
}
