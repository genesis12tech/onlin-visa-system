<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Reporting\Jobs\AggregateDailyApplicationMetrics;
use App\Domain\Reporting\Jobs\AggregateDailyPaymentMetrics;
use App\Domain\Reporting\Jobs\AggregateDocumentRejectionMetrics;
use App\Domain\Reporting\Jobs\AggregateOfficerPerformanceMetrics;
use App\Domain\Reporting\Models\DailyApplicationMetrics;
use App\Domain\Reporting\Models\DailyPaymentMetrics;
use App\Domain\Reporting\Models\DocumentRejectionMetrics;
use App\Domain\Reporting\Models\OfficerPerformanceMetrics;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MetricsAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregate_application_metrics_creates_row_for_each_visa_type(): void
    {
        $visaType = $this->makeVisaType();

        (new AggregateDailyApplicationMetrics('2026-01-01'))->handle();

        $row = DailyApplicationMetrics::where('visa_type_id', $visaType->ulid)->first();

        $this->assertNotNull($row);
        $this->assertEquals('2026-01-01', $row->date->toDateString());
    }

    public function test_aggregate_application_metrics_counts_submitted_on_date(): void
    {
        $visaType = $this->makeVisaType();

        $this->makeApplication($visaType, '2026-01-01 10:00:00');
        $this->makeApplication($visaType, '2026-01-01 14:00:00');
        $this->makeApplication($visaType, '2026-01-02 09:00:00');

        (new AggregateDailyApplicationMetrics('2026-01-01'))->handle();

        $row = DailyApplicationMetrics::where('visa_type_id', $visaType->ulid)->first();

        $this->assertNotNull($row);
        $this->assertEquals(2, $row->submitted_count);
    }

    public function test_aggregate_application_metrics_is_idempotent(): void
    {
        $this->makeVisaType();

        (new AggregateDailyApplicationMetrics('2026-01-01'))->handle();
        (new AggregateDailyApplicationMetrics('2026-01-01'))->handle();

        $this->assertDatabaseCount('daily_application_metrics', 1);
    }

    public function test_aggregate_payment_metrics_creates_row_per_currency(): void
    {
        $visaType = $this->makeVisaType();
        $app = $this->makeApplication($visaType, '2026-01-01 09:00:00');

        DB::table('payments')->insert([
            'ulid' => (string) Str::ulid(),
            'visa_application_id' => $app->ulid,
            'status' => PaymentStatus::Succeeded->value,
            'provider' => 'stripe',
            'amount_subtotal' => 15000,
            'amount_total' => 15000,
            'currency' => 'USD',
            'created_at' => '2026-01-01 12:00:00',
            'updated_at' => '2026-01-01 12:00:00',
        ]);

        (new AggregateDailyPaymentMetrics('2026-01-01'))->handle();

        $row = DailyPaymentMetrics::where('currency', 'USD')->first();

        $this->assertNotNull($row);
        $this->assertEquals('2026-01-01', $row->date->toDateString());
        $this->assertEquals(1, $row->succeeded_count);
        $this->assertEquals(15000, $row->total_collected);
    }

    public function test_aggregate_payment_metrics_is_idempotent(): void
    {
        $visaType = $this->makeVisaType();
        $app = $this->makeApplication($visaType, '2026-01-01 09:00:00');

        DB::table('payments')->insert([
            'ulid' => (string) Str::ulid(),
            'visa_application_id' => $app->ulid,
            'status' => PaymentStatus::Succeeded->value,
            'provider' => 'stripe',
            'amount_subtotal' => 10000,
            'amount_total' => 10000,
            'currency' => 'USD',
            'created_at' => '2026-01-01 12:00:00',
            'updated_at' => '2026-01-01 12:00:00',
        ]);

        (new AggregateDailyPaymentMetrics('2026-01-01'))->handle();
        (new AggregateDailyPaymentMetrics('2026-01-01'))->handle();

        $this->assertDatabaseCount('daily_payment_metrics', 1);
    }

    public function test_aggregate_officer_metrics_counts_decisions(): void
    {
        $officer = User::factory()->create();
        $visaType = $this->makeVisaType();
        $app = $this->makeApplication($visaType, '2026-01-01 09:00:00');

        ApplicationStatusHistory::create([
            'visa_application_id' => $app->ulid,
            'from_status' => ApplicationStatus::UnderReview->value,
            'to_status' => ApplicationStatus::Approved->value,
            'actor_id' => $officer->id,
            'created_at' => '2026-01-01 10:00:00',
        ]);

        ApplicationStatusHistory::create([
            'visa_application_id' => $app->ulid,
            'from_status' => ApplicationStatus::UnderReview->value,
            'to_status' => ApplicationStatus::Rejected->value,
            'actor_id' => $officer->id,
            'created_at' => '2026-01-01 11:00:00',
        ]);

        (new AggregateOfficerPerformanceMetrics('2026-01-01'))->handle();

        $row = OfficerPerformanceMetrics::where('officer_id', $officer->id)->first();

        $this->assertNotNull($row);
        $this->assertEquals('2026-01-01', $row->date->toDateString());
        $this->assertEquals(2, $row->reviewed_count);
        $this->assertEquals(1, $row->approved_count);
        $this->assertEquals(1, $row->rejected_count);
    }

    public function test_aggregate_document_rejection_metrics_counts_per_type(): void
    {
        $visaType = $this->makeVisaType();
        $app1 = $this->makeApplication($visaType, '2026-01-01 09:00:00');
        $app2 = $this->makeApplication($visaType, '2026-01-01 09:30:00');
        $docType = DocumentType::factory()->create();

        ApplicationDocument::create([
            'visa_application_id' => $app1->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Rejected,
            'reviewed_at' => '2026-01-01 10:00:00',
            'rejection_reason' => 'Image too blurry',
        ]);

        ApplicationDocument::create([
            'visa_application_id' => $app2->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Rejected,
            'reviewed_at' => '2026-01-01 10:00:00',
            'rejection_reason' => 'Image too blurry',
        ]);

        (new AggregateDocumentRejectionMetrics('2026-01-01'))->handle();

        $row = DocumentRejectionMetrics::where('document_type_id', $docType->ulid)->first();

        $this->assertNotNull($row);
        $this->assertEquals('2026-01-01', $row->date->toDateString());
        $this->assertEquals(2, $row->rejection_count);
    }

    private function makeVisaType(): VisaType
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);

        return VisaType::create([
            'name' => 'Tourist',
            'code' => 'TOUR30',
            'country_id' => $country->id,
            'processing_days' => 3,
            'validity_days' => 30,
        ]);
    }

    private function makeApplication(VisaType $type, string $submittedAt): VisaApplication
    {
        $form = FormTemplate::create([
            'visa_type_id' => $type->ulid,
            'name' => 'Form',
            'schema' => json_encode([]),
        ]);

        $user = User::factory()->create();
        $country = Country::firstOrCreate(
            ['iso2' => 'TE'],
            ['name' => 'Test', 'iso3' => 'TST']
        );

        $profile = ApplicantProfile::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
            'passport_number' => 'A'.rand(10000000, 99999999),
            'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890',
            'address_line_1' => '1 Test Street',
            'city' => 'London',
        ]);

        return VisaApplication::create([
            'tracking_number' => 'VA-TEST-'.rand(1000, 9999),
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => $submittedAt,
        ]);
    }
}
