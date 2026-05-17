<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReceiptDownloadControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ApplicantProfile $profile;

    private VisaApplication $application;

    private Payment $payment;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'finance_officer', 'guard_name' => 'web']);

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->assignRole('applicant');

        $this->profile = ApplicantProfile::factory()->create(['user_id' => $this->user->id]);

        $this->application = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status' => ApplicationStatus::PaymentCompleted,
        ]);

        $this->payment = Payment::factory()->succeeded()->create([
            'visa_application_id' => $this->application->ulid,
        ]);

        $this->invoice = Invoice::factory()->create([
            'payment_id' => $this->payment->ulid,
            'pdf_storage_path' => 'receipts/test-receipt.pdf',
        ]);
    }

    public function test_applicant_can_download_own_receipt(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('receipts/test-receipt.pdf', 'PDF content here');

        $this->actingAs($this->user)
            ->get(route('invoices.receipt', $this->invoice))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_receipt_download_writes_to_audit_log(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('receipts/test-receipt.pdf', 'PDF content here');

        $this->actingAs($this->user)
            ->get(route('invoices.receipt', $this->invoice));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'receipt.downloaded',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_other_applicant_cannot_download_receipt(): void
    {
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $otherUser->assignRole('applicant');

        $this->actingAs($otherUser)
            ->get(route('invoices.receipt', $this->invoice))
            ->assertForbidden();
    }

    public function test_finance_officer_can_download_any_receipt(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('receipts/test-receipt.pdf', 'PDF content here');

        $officer = User::factory()->create(['email_verified_at' => now()]);
        $officer->assignRole('finance_officer');

        $this->actingAs($officer)
            ->get(route('invoices.receipt', $this->invoice))
            ->assertOk();
    }

    public function test_redirects_when_pdf_not_yet_ready(): void
    {
        $invoice = Invoice::factory()->create([
            'payment_id' => $this->payment->ulid,
            'pdf_storage_path' => null,
        ]);

        $this->actingAs($this->user)
            ->get(route('invoices.receipt', $invoice))
            ->assertRedirect();
    }

    public function test_receipt_download_requires_authentication(): void
    {
        $this->get(route('invoices.receipt', $this->invoice))
            ->assertRedirect(route('login'));
    }
}
