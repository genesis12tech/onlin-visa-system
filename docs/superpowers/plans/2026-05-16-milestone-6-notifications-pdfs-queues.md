# Milestone 6: Notifications, PDFs & Queues — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire database+email notifications for all applicant-facing events, add three missing queued PDF generation jobs, activate all six named queues, and configure Horizon retry strategy on all jobs.

**Architecture:** All notifications implement `ShouldQueue` and send via both `mail` and `database` channels. PDF jobs store files to the private `documents` disk and write the path back to the owning model. Actions dispatch notifications and jobs **after** their DB transaction commits. No notification or PDF work ever runs synchronously in an HTTP request.

**Tech Stack:** Laravel 12, Filament 4, DomPDF (`Barryvdh\DomPDF`), Laravel Horizon, Redis, PHPUnit.

---

## What already exists (do not re-create)

| Item | File | Status |
|---|---|---|
| `ApplicationApprovedNotification` | `app/Notifications/ApplicationApprovedNotification.php` | ✅ Done |
| `ApplicationRejectedNotification` | `app/Notifications/ApplicationRejectedNotification.php` | ✅ Done |
| `AdditionalInfoRequestedNotification` | `app/Notifications/AdditionalInfoRequestedNotification.php` | ✅ Done |
| `AppointmentScheduledNotification` | `app/Notifications/AppointmentScheduledNotification.php` | ✅ Done |
| `GenerateReceiptPdf` (on `pdfs` queue) | `app/Domain/Payments/Jobs/GenerateReceiptPdf.php` | ✅ Done |
| Receipt dispatched from `HandlePaymentWebhook` | — | ✅ Done |
| Horizon supervisors with all 6 queue names | `config/horizon.php` | ✅ Done |

## What this milestone adds

| Queue | New job / notification |
|---|---|
| `high` | `ApplicationSubmittedNotification` |
| `default` | `PaymentSucceededNotification` |
| `emails` | `DocumentRejectedNotification` (all other notifications already use this) |
| `documents` | `ScanDocumentVersionJob` |
| `pdfs` | `GenerateDecisionLetterPdf`, `GenerateAppointmentConfirmationPdf`, `GenerateApplicationSummaryPdf` |
| `reports` | `ExportApplicationsJob` — already active |

---

## File Map

**New files — notifications:**
- `app/Notifications/ApplicationSubmittedNotification.php`
- `app/Notifications/PaymentSucceededNotification.php`
- `app/Notifications/DocumentRejectedNotification.php`

**New files — jobs:**
- `app/Domain/Documents/Jobs/ScanDocumentVersionJob.php`
- `app/Domain/Applications/Jobs/GenerateDecisionLetterPdf.php`
- `app/Domain/Applications/Jobs/GenerateAppointmentConfirmationPdf.php`
- `app/Domain/Applications/Jobs/GenerateApplicationSummaryPdf.php`

**New files — Blade templates:**
- `resources/views/pdfs/decision-letter.blade.php`
- `resources/views/pdfs/appointment-confirmation.blade.php`
- `resources/views/pdfs/application-summary.blade.php`

**New files — migrations:**
- `database/migrations/YYYY_MM_DD_add_pdf_paths_to_visa_applications_table.php` (adds `decision_letter_pdf_path`, `summary_pdf_path`)
- `database/migrations/YYYY_MM_DD_add_confirmation_pdf_path_to_application_appointments_table.php`

**New files — tests:**
- `tests/Feature/ApplicationSubmittedNotificationTest.php`
- `tests/Feature/PaymentSucceededNotificationTest.php`
- `tests/Feature/DocumentRejectedNotificationTest.php`
- `tests/Feature/ScanDocumentVersionJobTest.php`
- `tests/Feature/GenerateDecisionLetterPdfTest.php`
- `tests/Feature/GenerateAppointmentConfirmationPdfTest.php`
- `tests/Feature/GenerateApplicationSummaryPdfTest.php`

**Modified files — actions (dispatch wiring):**
- `app/Domain/Applications/Actions/SubmitApplication.php` — dispatch `ApplicationSubmittedNotification` + `GenerateApplicationSummaryPdf`
- `app/Domain/Applications/Actions/ApproveApplication.php` — dispatch `GenerateDecisionLetterPdf`
- `app/Domain/Applications/Actions/RejectApplication.php` — dispatch `GenerateDecisionLetterPdf`
- `app/Domain/Applications/Actions/ScheduleAppointment.php` — dispatch `GenerateAppointmentConfirmationPdf`
- `app/Domain/Documents/Actions/RejectDocument.php` — dispatch `DocumentRejectedNotification`
- `app/Domain/Documents/Actions/UploadDocumentVersion.php` — dispatch `ScanDocumentVersionJob`
- `app/Domain/Payments/Actions/HandlePaymentWebhook.php` — dispatch `PaymentSucceededNotification`

**Modified files — models (fillable + casts):**
- `app/Domain/Applications/Models/VisaApplication.php` — add `decision_letter_pdf_path`, `summary_pdf_path` to `$fillable`
- `app/Domain/Applications/Models/ApplicationAppointment.php` — add `confirmation_pdf_path` to `$fillable`

**Modified files — retry strategy:**
- `app/Domain/Payments/Jobs/GenerateReceiptPdf.php` — add `$tries`, `$timeout`, `$backoff`
- All 4 existing notification classes — add `$tries`, `$timeout`, `$backoff`

---

## Task 1: ApplicationSubmittedNotification

**Files:**
- Create: `app/Notifications/ApplicationSubmittedNotification.php`
- Modify: `app/Domain/Applications/Actions/SubmitApplication.php`
- Test: `tests/Feature/ApplicationSubmittedNotificationTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\SubmitApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use App\Notifications\ApplicationSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApplicationSubmittedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_dispatches_submitted_notification_to_applicant(): void
    {
        Notification::fake();

        ['application' => $application, 'applicantUser' => $applicantUser] = $this->makeContext();

        (new SubmitApplication)->execute($application, User::factory()->create());

        Notification::assertSentTo($applicantUser, ApplicationSubmittedNotification::class);
    }

    public function test_submitted_notification_is_on_high_queue(): void
    {
        $country = Country::create(['name' => 'T', 'iso2' => 'TX', 'iso3' => 'TXX']);
        $type = VisaType::create(['name' => 'T', 'code' => 'T1', 'country_id' => $country->id, 'processing_days' => 1, 'validity_days' => 30]);
        $form = FormTemplate::create(['visa_type_id' => $type->ulid, 'name' => 'F', 'schema' => json_encode([])]);
        $application = VisaApplication::create([
            'tracking_number' => 'VA-QUEUE-TEST',
            'applicant_profile_id' => ApplicantProfile::create([
                'user_id' => User::factory()->create()->id,
                'first_name' => 'A', 'last_name' => 'B', 'date_of_birth' => '1990-01-01',
                'gender' => 'male', 'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
                'passport_number' => 'P1234567', 'passport_expiry_date' => '2030-01-01',
                'phone' => '+1234567890', 'address_line_1' => '1 St', 'city' => 'X',
            ])->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Draft,
        ]);

        $notification = new ApplicationSubmittedNotification($application);

        $this->assertEquals('high', $notification->queue);
    }

    public function test_submitted_notification_database_payload_contains_tracking_number(): void
    {
        Notification::fake();

        ['application' => $application, 'applicantUser' => $applicantUser] = $this->makeContext();

        (new SubmitApplication)->execute($application, User::factory()->create());

        Notification::assertSentTo(
            $applicantUser,
            ApplicationSubmittedNotification::class,
            function (ApplicationSubmittedNotification $n) use ($applicantUser, $application) {
                $data = $n->toArray($applicantUser);

                return $data['type'] === 'application_submitted'
                    && $data['tracking_number'] === $application->tracking_number;
            }
        );
    }

    private function makeContext(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $type = VisaType::create(['name' => 'Tourist', 'code' => 'TOURIST_30', 'country_id' => $country->id, 'processing_days' => 3, 'validity_days' => 30]);
        $form = FormTemplate::create(['visa_type_id' => $type->ulid, 'name' => 'Tourist Form', 'schema' => json_encode([])]);
        $applicantUser = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $applicantUser->id,
            'first_name' => 'Test', 'last_name' => 'Applicant',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'A12345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Test Street', 'city' => 'London',
        ]);
        $application = VisaApplication::create([
            'tracking_number' => 'VA-SUBMIT-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Draft,
        ]);

        return compact('application', 'applicantUser');
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --compact --filter=ApplicationSubmittedNotificationTest
```

Expected: FAIL — `ApplicationSubmittedNotification` class not found.

- [ ] **Step 3: Create the notification class**

Create `app/Notifications/ApplicationSubmittedNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 30;
    public array $backoff = [5, 15, 30];

    public function __construct(public readonly VisaApplication $application)
    {
        $this->queue = 'high';
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your visa application has been received')
            ->greeting('Dear '.$notifiable->name.',')
            ->line('We have received your visa application ('.$this->application->tracking_number.').')
            ->line('Your application is now under review. We will notify you of any updates.')
            ->action('Track Your Application', url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application_submitted',
            'visa_application_id' => $this->application->ulid,
            'tracking_number' => $this->application->tracking_number,
            'message' => 'Your application ('.$this->application->tracking_number.') has been received.',
        ];
    }
}
```

- [ ] **Step 4: Wire notification into SubmitApplication**

Open `app/Domain/Applications/Actions/SubmitApplication.php`. The current `execute()` method returns inside the `DB::transaction()`. Replace the full method with this version that dispatches the notification after the transaction:

```php
<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\User;
use App\Notifications\ApplicationSubmittedNotification;
use Illuminate\Support\Facades\DB;

class SubmitApplication
{
    public function execute(VisaApplication $application, User $actor): VisaApplication
    {
        DB::transaction(function () use ($application, $actor) {
            $blockingDocExists = $application->documents()
                ->whereIn('status', [
                    DocumentStatus::Pending->value,
                    DocumentStatus::Rejected->value,
                    DocumentStatus::Infected->value,
                ])
                ->exists();

            if ($blockingDocExists) {
                throw new \RuntimeException('All required documents must be uploaded before submission.');
            }

            $fromStatus = $application->status->value;

            $application->update([
                'status' => ApplicationStatus::Submitted,
                'submitted_at' => now(),
            ]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => $fromStatus,
                'to_status' => ApplicationStatus::Submitted->value,
                'actor_id' => $actor->id,
                'created_at' => now(),
            ]);
        });

        $application->refresh();

        $application->load('applicantProfile.user');
        $applicantUser = $application->applicantProfile?->user;

        if ($applicantUser) {
            $applicantUser->notify(new ApplicationSubmittedNotification($application));
        }

        return $application;
    }
}
```

- [ ] **Step 5: Run tests — all should pass**

```bash
php artisan test --compact --filter=ApplicationSubmittedNotificationTest
```

Expected: 3 PASS

- [ ] **Step 6: Run full suite to check for regressions**

```bash
php artisan test --compact --filter=SubmitApplication
```

Expected: All PASS (the existing `SubmitApplicationDocumentCheckTest` tests must still pass).

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications/ApplicationSubmittedNotification.php \
        app/Domain/Applications/Actions/SubmitApplication.php \
        tests/Feature/ApplicationSubmittedNotificationTest.php
git commit -m "feat(m6): add ApplicationSubmittedNotification dispatched on submit (high queue)"
```

---

## Task 2: PaymentSucceededNotification

**Files:**
- Create: `app/Notifications/PaymentSucceededNotification.php`
- Modify: `app/Domain/Payments/Actions/HandlePaymentWebhook.php`
- Test: add test to `tests/Feature/HandlePaymentWebhookTest.php`

- [ ] **Step 1: Write the failing test**

Add two test methods to the existing `HandlePaymentWebhookTest` class (after the last existing method, before the closing brace):

```php
public function test_checkout_completed_dispatches_payment_succeeded_notification(): void
{
    Notification::fake();
    [$application, $payment] = $this->makeApplicationAndPayment();

    (new HandlePaymentWebhook)->execute(
        $this->makeEvent('checkout.session.completed', [
            'id' => 'cs_test_notify01',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_test_notify01',
            'metadata' => ['visa_application_ulid' => $application->ulid],
        ])
    );

    $applicantUser = $application->applicantProfile->user;
    Notification::assertSentTo($applicantUser, \App\Notifications\PaymentSucceededNotification::class);
}

public function test_payment_succeeded_notification_is_on_default_queue(): void
{
    $country = \App\Domain\Identity\Models\Country::create(['name' => 'QN', 'iso2' => 'QN', 'iso3' => 'QNN']);
    $type = \App\Domain\Applications\Models\VisaType::create(['name' => 'T', 'code' => 'QT1', 'country_id' => $country->id, 'processing_days' => 1, 'validity_days' => 30]);
    $form = \App\Domain\Applications\Models\FormTemplate::create(['visa_type_id' => $type->ulid, 'name' => 'F', 'schema' => json_encode([])]);
    $user = User::factory()->create();
    $profile = \App\Domain\Identity\Models\ApplicantProfile::create([
        'user_id' => $user->id, 'first_name' => 'A', 'last_name' => 'B',
        'date_of_birth' => '1990-01-01', 'gender' => 'male',
        'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
        'passport_number' => 'Q1234567', 'passport_expiry_date' => '2030-01-01',
        'phone' => '+1234567890', 'address_line_1' => '1 St', 'city' => 'X',
    ]);
    $application = \App\Domain\Applications\Models\VisaApplication::create([
        'tracking_number' => 'VA-QN-001',
        'applicant_profile_id' => $profile->ulid,
        'visa_type_id' => $type->ulid,
        'form_template_id' => $form->ulid,
        'status' => \App\Domain\Applications\Enums\ApplicationStatus::PaymentPending,
    ]);
    $payment = \App\Domain\Payments\Models\Payment::create([
        'visa_application_id' => $application->ulid,
        'status' => \App\Domain\Payments\Enums\PaymentStatus::Succeeded,
        'provider' => 'stripe',
        'provider_checkout_session_id' => 'cs_qn_001',
        'amount_subtotal' => 5000,
        'amount_total' => 5000,
        'currency' => 'USD',
    ]);
    $invoice = \App\Domain\Payments\Models\Invoice::create([
        'payment_id' => $payment->ulid,
        'invoice_number' => 'INV-2026-000001',
        'issued_at' => now(),
    ]);

    $notification = new \App\Notifications\PaymentSucceededNotification($payment, $invoice);

    $this->assertEquals('default', $notification->queue);
}
```

Also add the `Notification` import at the top of the test file:
```php
use Illuminate\Support\Facades\Notification;
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --compact --filter=test_checkout_completed_dispatches_payment_succeeded_notification
```

Expected: FAIL — `PaymentSucceededNotification` class not found.

- [ ] **Step 3: Create the notification class**

Create `app/Notifications/PaymentSucceededNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSucceededNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 30;
    public array $backoff = [5, 15, 30];

    public function __construct(
        public readonly Payment $payment,
        public readonly Invoice $invoice,
    ) {
        $this->queue = 'default';
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->payment->amount_total / 100, 2).' '.strtoupper($this->payment->currency);
        $tracking = $this->payment->visaApplication->tracking_number ?? '—';

        return (new MailMessage)
            ->subject('Payment received — Invoice #'.$this->invoice->invoice_number)
            ->greeting('Dear '.$notifiable->name.',')
            ->line('We have received your payment of '.$amount.' for application ('.$tracking.').')
            ->line('Invoice: '.$this->invoice->invoice_number)
            ->action('View Application', url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_succeeded',
            'visa_application_id' => $this->payment->visaApplication->ulid ?? null,
            'tracking_number' => $this->payment->visaApplication->tracking_number ?? null,
            'invoice_number' => $this->invoice->invoice_number,
            'amount_total' => $this->payment->amount_total,
            'currency' => $this->payment->currency,
            'message' => 'Payment of '.number_format($this->payment->amount_total / 100, 2).' '.strtoupper($this->payment->currency).' received.',
        ];
    }
}
```

- [ ] **Step 4: Wire notification into HandlePaymentWebhook**

In `HandlePaymentWebhook`, add a `notifyPaymentSucceeded` private method and call it after the transaction in `execute()`. The full updated file:

```php
<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Jobs\GenerateReceiptPdf;
use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentWebhookEvent;
use App\Notifications\PaymentSucceededNotification;
use Illuminate\Support\Facades\DB;

class HandlePaymentWebhook
{
    private ?string $succeededSessionId = null;

    public function execute(object $event): void
    {
        $webhookEvent = PaymentWebhookEvent::firstOrCreate(
            ['event_id' => $event->id],
            [
                'provider' => 'stripe',
                'event_type' => $event->type,
                'payload' => json_decode(json_encode($event), true),
                'created_at' => now(),
            ]
        );

        if ($webhookEvent->processed_at !== null) {
            return;
        }

        try {
            DB::transaction(function () use ($event, $webhookEvent): void {
                match ($event->type) {
                    'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
                    'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
                    default => null,
                };

                $webhookEvent->markProcessed();
            });
        } catch (\Throwable $e) {
            $webhookEvent->fresh()?->markFailed($e->getMessage());
            throw $e;
        }

        if ($this->succeededSessionId !== null) {
            $this->notifyPaymentSucceeded($this->succeededSessionId);
        }
    }

    private function handleCheckoutCompleted(object $session): void
    {
        if (($session->payment_status ?? '') !== 'paid') {
            return;
        }

        $payment = Payment::where('provider_checkout_session_id', $session->id)->first();

        if (! $payment || $payment->status === PaymentStatus::Succeeded) {
            return;
        }

        $payment->update([
            'status' => PaymentStatus::Succeeded,
            'provider_payment_intent_id' => $session->payment_intent ?? null,
            'succeeded_at' => now(),
        ]);

        $application = $payment->visaApplication;

        if (! $application) {
            throw new \RuntimeException("Payment {$payment->ulid} has no associated visa application.");
        }

        $application->update(['status' => ApplicationStatus::PaymentCompleted]);

        ApplicationStatusHistory::create([
            'visa_application_id' => $application->ulid,
            'from_status' => ApplicationStatus::PaymentPending->value,
            'to_status' => ApplicationStatus::PaymentCompleted->value,
            'actor_id' => null,
            'created_at' => now(),
        ]);

        $invoice = Invoice::create([
            'payment_id' => $payment->ulid,
            'invoice_number' => $this->generateInvoiceNumber(),
            'issued_at' => now(),
        ]);

        GenerateReceiptPdf::dispatch($invoice->ulid)->onQueue('pdfs');

        $this->succeededSessionId = $session->id;
    }

    private function handlePaymentFailed(object $intent): void
    {
        $failureMessage = $intent->last_payment_error->message ?? 'Payment failed';

        $payment = Payment::where('provider_payment_intent_id', $intent->id)->first();

        if (! $payment) {
            return;
        }

        $payment->update([
            'status' => PaymentStatus::Failed,
            'failure_reason' => $failureMessage,
        ]);
    }

    private function notifyPaymentSucceeded(string $sessionId): void
    {
        $payment = Payment::with(['visaApplication.applicantProfile.user', 'invoice'])
            ->where('provider_checkout_session_id', $sessionId)
            ->first();

        if (! $payment || ! $payment->invoice) {
            return;
        }

        $applicantUser = $payment->visaApplication?->applicantProfile?->user;

        if ($applicantUser) {
            $applicantUser->notify(new PaymentSucceededNotification($payment, $payment->invoice));
        }
    }

    private function generateInvoiceNumber(): string
    {
        $year = now()->format('Y');
        $maxAttempts = 10;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $count = Invoice::whereYear('issued_at', $year)->count() + $attempt;
            $candidate = 'INV-'.$year.'-'.str_pad((string) $count, 6, '0', STR_PAD_LEFT);

            if (! Invoice::where('invoice_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'INV-'.$year.'-'.now()->format('Hisu');
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=HandlePaymentWebhookTest
```

Expected: All PASS (existing + 2 new).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications/PaymentSucceededNotification.php \
        app/Domain/Payments/Actions/HandlePaymentWebhook.php \
        tests/Feature/HandlePaymentWebhookTest.php
git commit -m "feat(m6): add PaymentSucceededNotification dispatched after payment webhook (default queue)"
```

---

## Task 3: DocumentRejectedNotification

**Files:**
- Create: `app/Notifications/DocumentRejectedNotification.php`
- Modify: `app/Domain/Documents/Actions/RejectDocument.php`
- Test: `tests/Feature/DocumentRejectedNotificationTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Actions\RejectDocument;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use App\Notifications\DocumentRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DocumentRejectedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reject_document_dispatches_notification_to_applicant(): void
    {
        Notification::fake();

        ['document' => $document, 'applicantUser' => $applicantUser, 'officer' => $officer] = $this->makeContext();

        (new RejectDocument)->execute($document, $officer, 'Photo too dark.');

        Notification::assertSentTo($applicantUser, DocumentRejectedNotification::class);
    }

    public function test_rejected_document_notification_contains_rejection_reason(): void
    {
        Notification::fake();

        ['document' => $document, 'applicantUser' => $applicantUser, 'officer' => $officer] = $this->makeContext();

        (new RejectDocument)->execute($document, $officer, 'Blurry image.');

        Notification::assertSentTo(
            $applicantUser,
            DocumentRejectedNotification::class,
            function (DocumentRejectedNotification $n) use ($applicantUser) {
                $data = $n->toArray($applicantUser);

                return $data['type'] === 'document_rejected'
                    && $data['reason'] === 'Blurry image.';
            }
        );
    }

    public function test_document_rejected_notification_is_on_emails_queue(): void
    {
        $country = Country::create(['name' => 'T', 'iso2' => 'DT', 'iso3' => 'DTT']);
        $type = VisaType::create(['name' => 'T', 'code' => 'DT1', 'country_id' => $country->id, 'processing_days' => 1, 'validity_days' => 30]);
        $form = FormTemplate::create(['visa_type_id' => $type->ulid, 'name' => 'F', 'schema' => json_encode([])]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id, 'first_name' => 'A', 'last_name' => 'B',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'D1234567', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 St', 'city' => 'X',
        ]);
        $application = VisaApplication::create([
            'tracking_number' => 'VA-DT-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Submitted,
        ]);
        $docType = DocumentType::factory()->create();
        $document = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Uploaded,
        ]);

        $notification = new DocumentRejectedNotification($document, 'Bad photo.');

        $this->assertEquals('emails', $notification->queue);
    }

    private function makeContext(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $type = VisaType::create(['name' => 'Tourist', 'code' => 'TOURIST_DR', 'country_id' => $country->id, 'processing_days' => 3, 'validity_days' => 30]);
        $form = FormTemplate::create(['visa_type_id' => $type->ulid, 'name' => 'Tourist Form', 'schema' => json_encode([])]);
        $applicantUser = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $applicantUser->id,
            'first_name' => 'Test', 'last_name' => 'Applicant',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'A12345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Test Street', 'city' => 'London',
        ]);
        $application = VisaApplication::create([
            'tracking_number' => 'VA-DR-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Submitted,
        ]);
        $docType = DocumentType::factory()->create();
        $document = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Uploaded,
        ]);
        $officer = User::factory()->create();

        return compact('document', 'applicantUser', 'officer');
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --compact --filter=DocumentRejectedNotificationTest
```

Expected: FAIL — `DocumentRejectedNotification` not found.

- [ ] **Step 3: Create the notification class**

Create `app/Notifications/DocumentRejectedNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Domain\Documents\Models\ApplicationDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 30;
    public array $backoff = [5, 15, 30];

    public function __construct(
        public readonly ApplicationDocument $document,
        public readonly string $reason,
    ) {
        $this->queue = 'emails';
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $documentTypeName = $this->document->documentType->name ?? 'document';
        $trackingNumber = $this->document->visaApplication->tracking_number ?? '—';

        return (new MailMessage)
            ->subject('Document rejected — action required')
            ->greeting('Dear '.$notifiable->name.',')
            ->line('Your '.$documentTypeName.' for application ('.$trackingNumber.') has been rejected.')
            ->line('Reason: '.$this->reason)
            ->line('Please upload a replacement document to continue your application.')
            ->action('Upload Document', url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'document_rejected',
            'application_document_id' => $this->document->ulid,
            'visa_application_id' => $this->document->visa_application_id,
            'document_type' => $this->document->documentType->name ?? null,
            'reason' => $this->reason,
            'message' => 'Your document has been rejected: '.$this->reason,
        ];
    }
}
```

- [ ] **Step 4: Wire notification into RejectDocument**

Replace the full contents of `app/Domain/Documents/Actions/RejectDocument.php`:

```php
<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Models\User;
use App\Notifications\DocumentRejectedNotification;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class RejectDocument
{
    public function execute(ApplicationDocument $document, User $officer, string $reason): ApplicationDocument
    {
        DB::transaction(function () use ($document, $officer, $reason) {
            $document->update([
                'status' => DocumentStatus::Rejected,
                'reviewed_by' => $officer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            AuditLogger::log('document.rejected', $document, ['reason' => $reason], $officer->id);
        });

        $document->refresh();
        $document->load('visaApplication.applicantProfile.user', 'documentType');

        $applicantUser = $document->visaApplication?->applicantProfile?->user;

        if ($applicantUser) {
            $applicantUser->notify(new DocumentRejectedNotification($document, $reason));
        }

        return $document;
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=DocumentRejectedNotificationTest
```

Expected: 3 PASS

- [ ] **Step 6: Confirm existing reject-document tests still pass**

```bash
php artisan test --compact --filter=AcceptRejectDocumentActionTest
```

Expected: All PASS.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications/DocumentRejectedNotification.php \
        app/Domain/Documents/Actions/RejectDocument.php \
        tests/Feature/DocumentRejectedNotificationTest.php
git commit -m "feat(m6): add DocumentRejectedNotification dispatched on document rejection (emails queue)"
```

---

## Task 4: ScanDocumentVersionJob (activates `documents` queue)

**Files:**
- Create: `app/Domain/Documents/Jobs/ScanDocumentVersionJob.php`
- Modify: `app/Domain/Documents/Actions/UploadDocumentVersion.php`
- Test: `tests/Feature/ScanDocumentVersionJobTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Actions\UploadDocumentVersion;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Jobs\ScanDocumentVersionJob;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Documents\Models\DocumentVersion;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScanDocumentVersionJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    public function test_upload_dispatches_scan_job_to_documents_queue(): void
    {
        Queue::fake();

        [$docSlot, $uploader] = $this->makeDocumentSlot();
        $file = UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf');

        (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);

        Queue::assertPushedOn('documents', ScanDocumentVersionJob::class);
    }

    public function test_scan_job_marks_version_as_clean(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();
        $country = Country::first();
        $type = VisaType::first();
        $form = FormTemplate::first();

        $version = DocumentVersion::create([
            'application_document_id' => $docSlot->ulid,
            'storage_path' => 'documents/test.pdf',
            'original_filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 100,
            'sha256_checksum' => hash('sha256', 'test'),
            'scan_status' => ScanStatus::Pending,
            'uploaded_by' => $uploader->id,
            'created_at' => now(),
        ]);

        (new ScanDocumentVersionJob($version->ulid))->handle();

        $this->assertEquals(ScanStatus::Clean, $version->fresh()->scan_status);
        $this->assertNotNull($version->fresh()->scan_completed_at);
    }

    public function test_scan_job_is_idempotent_if_already_scanned(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        $version = DocumentVersion::create([
            'application_document_id' => $docSlot->ulid,
            'storage_path' => 'documents/test2.pdf',
            'original_filename' => 'test2.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 100,
            'sha256_checksum' => hash('sha256', 'test2'),
            'scan_status' => ScanStatus::Clean,
            'scan_completed_at' => now()->subMinute(),
            'uploaded_by' => $uploader->id,
            'created_at' => now(),
        ]);

        $originalCompletedAt = $version->scan_completed_at;

        (new ScanDocumentVersionJob($version->ulid))->handle();

        // scan_completed_at should not change if already clean
        $this->assertEquals(
            $originalCompletedAt->toIso8601String(),
            $version->fresh()->scan_completed_at->toIso8601String()
        );
    }

    private function makeDocumentSlot(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'SC', 'iso3' => 'SCC']);
        $type = VisaType::create(['name' => 'Tourist', 'code' => 'SCAN_30', 'country_id' => $country->id, 'processing_days' => 3, 'validity_days' => 30]);
        $form = FormTemplate::create(['visa_type_id' => $type->ulid, 'name' => 'Form', 'schema' => json_encode([])]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id, 'first_name' => 'Scan', 'last_name' => 'Test',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'S12345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Scan St', 'city' => 'London',
        ]);
        VisaApplication::create([
            'tracking_number' => 'VA-SCAN-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Draft,
        ]);
        $docType = DocumentType::factory()->create(['accepted_mime_types' => ['application/pdf'], 'max_size_kb' => 5120]);
        $application = VisaApplication::where('tracking_number', 'VA-SCAN-001')->first();
        $docSlot = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => \App\Domain\Documents\Enums\DocumentStatus::Pending,
        ]);
        $uploader = User::factory()->create();

        return [$docSlot, $uploader];
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --compact --filter=ScanDocumentVersionJobTest
```

Expected: FAIL — `ScanDocumentVersionJob` not found.

- [ ] **Step 3: Create the job**

Create `app/Domain/Documents/Jobs/ScanDocumentVersionJob.php`:

```php
<?php

namespace App\Domain\Documents\Jobs;

use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Models\DocumentVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScanDocumentVersionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;
    public array $backoff = [5, 15, 30];

    public function __construct(public readonly string $documentVersionUlid) {}

    public function handle(): void
    {
        $version = DocumentVersion::findOrFail($this->documentVersionUlid);

        if ($version->scan_status !== ScanStatus::Pending) {
            return;
        }

        // MVP: simulate a clean scan. Replace with a real AV API call in production.
        $version->update([
            'scan_status' => ScanStatus::Clean,
            'scan_completed_at' => now(),
        ]);
    }
}
```

- [ ] **Step 4: Wire job into UploadDocumentVersion**

Replace the full contents of `app/Domain/Documents/Actions/UploadDocumentVersion.php`:

```php
<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Jobs\ScanDocumentVersionJob;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Documents\Models\DocumentVersion;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadDocumentVersion
{
    public function execute(
        ApplicationDocument $document,
        UploadedFile $file,
        User $uploader,
    ): DocumentVersion {
        $this->validateFile($file, $document->documentType);

        $contents = file_get_contents($file->getRealPath());
        $sha256 = hash('sha256', $contents);

        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $storagePath = 'documents/'.Str::ulid().'.'.$ext;

        Storage::disk('documents')->put($storagePath, $contents);

        try {
            $version = DB::transaction(function () use ($document, $file, $uploader, $sha256, $storagePath) {
                $version = DocumentVersion::create([
                    'application_document_id' => $document->ulid,
                    'storage_path' => $storagePath,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size_bytes' => $file->getSize(),
                    'sha256_checksum' => $sha256,
                    'scan_status' => ScanStatus::Pending,
                    'uploaded_by' => $uploader->id,
                    'created_at' => now(),
                ]);

                $document->update([
                    'current_version_id' => $version->ulid,
                    'status' => DocumentStatus::Uploaded,
                ]);

                AuditLogger::log(
                    'document.uploaded',
                    $document,
                    [
                        'version_ulid' => $version->ulid,
                        'original_filename' => $file->getClientOriginalName(),
                    ],
                    $uploader->id,
                );

                return $version;
            });
        } catch (\Throwable $e) {
            Storage::disk('documents')->delete($storagePath);
            throw $e;
        }

        ScanDocumentVersionJob::dispatch($version->ulid)->onQueue('documents');

        return $version;
    }

    private function validateFile(UploadedFile $file, DocumentType $documentType): void
    {
        $mimeType = $file->getMimeType();

        if (! in_array($mimeType, $documentType->accepted_mime_types)) {
            throw ValidationException::withMessages([
                'file' => ['File type not accepted. Accepted: '.implode(', ', $documentType->accepted_mime_types)],
            ]);
        }

        $fileSizeKb = (int) ceil($file->getSize() / 1024);

        if ($fileSizeKb > $documentType->max_size_kb) {
            throw ValidationException::withMessages([
                'file' => ['File exceeds the maximum allowed size of '.$documentType->max_size_kb.'KB.'],
            ]);
        }
    }
}
```

- [ ] **Step 5: Run all document-related tests**

```bash
php artisan test --compact --filter=ScanDocumentVersionJobTest
php artisan test --compact --filter=UploadDocumentVersionTest
```

Expected: All PASS. The existing upload tests still pass because they don't assert on queue behavior (`Queue::fake()` is not called in the existing tests, so the job simply dispatches to the fake queue silently — but with `Queue::fake()` not set, it tries to dispatch for real; since no queue worker is running in tests, it's fine because the tests don't block on it).

**NOTE:** Existing `UploadDocumentVersionTest` tests do NOT call `Queue::fake()`. When `ScanDocumentVersionJob` is dispatched in tests without `Queue::fake()`, Laravel uses the `sync` driver by default in the test environment (check `config/queue.php` default). If the default driver is `sync`, the job runs immediately in the test. Verify `QUEUE_CONNECTION=sync` in `.env.testing` or `phpunit.xml`. If it fails, add `Queue::fake()` to `setUp()` in `UploadDocumentVersionTest` — but do not remove any existing assertions.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Documents/Jobs/ScanDocumentVersionJob.php \
        app/Domain/Documents/Actions/UploadDocumentVersion.php \
        tests/Feature/ScanDocumentVersionJobTest.php
git commit -m "feat(m6): add ScanDocumentVersionJob on documents queue; dispatch from UploadDocumentVersion"
```

---

## Task 5: GenerateDecisionLetterPdf

**Files:**
- Create migration: `add_decision_letter_pdf_path_to_visa_applications_table.php`
- Create: `app/Domain/Applications/Jobs/GenerateDecisionLetterPdf.php`
- Create: `resources/views/pdfs/decision-letter.blade.php`
- Modify: `app/Domain/Applications/Models/VisaApplication.php` (add to `$fillable`)
- Modify: `app/Domain/Applications/Actions/ApproveApplication.php` (dispatch job)
- Modify: `app/Domain/Applications/Actions/RejectApplication.php` (dispatch job)
- Test: `tests/Feature/GenerateDecisionLetterPdfTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\ApproveApplication;
use App\Domain\Applications\Actions\RejectApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Jobs\GenerateDecisionLetterPdf;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Models\DocumentVersion;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GenerateDecisionLetterPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_approve_action_dispatches_decision_letter_pdf_job(): void
    {
        Queue::fake();

        $actor = User::factory()->create();
        $actor->assignRole('super_admin');
        $application = $this->makeSubmittedApplication();

        (new ApproveApplication)->execute($application, $actor);

        Queue::assertPushedOn('pdfs', GenerateDecisionLetterPdf::class);
    }

    public function test_reject_action_dispatches_decision_letter_pdf_job(): void
    {
        Queue::fake();

        $actor = User::factory()->create();
        $application = $this->makeSubmittedApplication();

        (new RejectApplication)->execute($application, $actor, 'Missing documents');

        Queue::assertPushedOn('pdfs', GenerateDecisionLetterPdf::class);
    }

    public function test_decision_letter_job_generates_pdf_and_stores_path(): void
    {
        Storage::fake('documents');

        $application = $this->makeSubmittedApplication();
        $application->update(['status' => ApplicationStatus::Approved, 'decision_at' => now()]);

        (new GenerateDecisionLetterPdf($application->ulid))->handle();

        $this->assertNotNull($application->fresh()->decision_letter_pdf_path);
        Storage::disk('documents')->assertExists($application->fresh()->decision_letter_pdf_path);
    }

    private function makeSubmittedApplication(): VisaApplication
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'DL', 'iso3' => 'DLT']);
        $type = VisaType::create([
            'name' => 'Tourist', 'code' => 'DL_30', 'country_id' => $country->id,
            'processing_days' => 3, 'validity_days' => 30,
        ]);
        $form = FormTemplate::create(['visa_type_id' => $type->ulid, 'name' => 'Tourist Form', 'schema' => json_encode([])]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id, 'first_name' => 'Decision', 'last_name' => 'Test',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'DL345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Test St', 'city' => 'London',
        ]);

        return VisaApplication::create([
            'tracking_number' => 'VA-DL-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --compact --filter=GenerateDecisionLetterPdfTest
```

Expected: FAIL — `GenerateDecisionLetterPdf` class not found.

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration add_decision_letter_pdf_path_to_visa_applications_table --no-interaction
```

Edit the generated migration file to add this `up()` method:

```php
public function up(): void
{
    Schema::table('visa_applications', function (Blueprint $table) {
        $table->string('decision_letter_pdf_path')->nullable()->after('decision_reason');
    });
}

public function down(): void
{
    Schema::table('visa_applications', function (Blueprint $table) {
        $table->dropColumn('decision_letter_pdf_path');
    });
}
```

Run it:

```bash
php artisan migrate --no-interaction
```

- [ ] **Step 4: Update VisaApplication model fillable**

In `app/Domain/Applications/Models/VisaApplication.php`, add `'decision_letter_pdf_path'` to the `$fillable` array:

```php
protected $fillable = [
    'tracking_number',
    'applicant_profile_id',
    'visa_type_id',
    'form_template_id',
    'status',
    'assigned_officer_id',
    'submitted_at',
    'travel_date',
    'decision_at',
    'decision_reason',
    'decision_letter_pdf_path',
];
```

- [ ] **Step 5: Create the Blade template**

Create `resources/views/pdfs/decision-letter.blade.php`:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1a1a1a; margin: 40px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .subtitle { color: #6b7280; margin-bottom: 32px; }
        .field { margin-bottom: 12px; }
        .label { color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
        .value { font-size: 14px; margin-top: 2px; }
        .decision-box { padding: 16px; margin: 24px 0; border-radius: 4px; }
        .approved { background: #d1fae5; border-left: 4px solid #10b981; }
        .rejected { background: #fee2e2; border-left: 4px solid #ef4444; }
        .footer { margin-top: 48px; font-size: 11px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 16px; }
    </style>
</head>
<body>
    <h1>Visa Application Decision</h1>
    <p class="subtitle">Reference: {{ $application->tracking_number }}</p>

    <div class="field">
        <div class="label">Applicant</div>
        <div class="value">{{ $applicantProfile->full_name }}</div>
    </div>
    <div class="field">
        <div class="label">Visa Type</div>
        <div class="value">{{ $application->visaType->name }}</div>
    </div>
    <div class="field">
        <div class="label">Decision Date</div>
        <div class="value">{{ $application->decision_at?->format('d M Y') ?? '—' }}</div>
    </div>

    <div class="decision-box {{ $application->status->value === 'approved' ? 'approved' : 'rejected' }}">
        <strong>Decision: {{ ucfirst($application->status->value) }}</strong>
        @if($application->decision_reason)
            <p style="margin: 8px 0 0;">{{ $application->decision_reason }}</p>
        @endif
    </div>

    <div class="footer">
        Generated on {{ now()->format('d M Y') }}. Tracking number: {{ $application->tracking_number }}.
    </div>
</body>
</html>
```

- [ ] **Step 6: Create the job class**

Create `app/Domain/Applications/Jobs/GenerateDecisionLetterPdf.php`:

```php
<?php

namespace App\Domain\Applications\Jobs;

use App\Domain\Applications\Models\VisaApplication;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateDecisionLetterPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly string $visaApplicationUlid) {}

    public function handle(): void
    {
        $application = VisaApplication::with(['visaType', 'applicantProfile'])
            ->findOrFail($this->visaApplicationUlid);

        $applicantProfile = $application->applicantProfile;

        $pdf = Pdf::loadView('pdfs.decision-letter', compact('application', 'applicantProfile'));

        $storagePath = 'decisions/'.Str::ulid().'.pdf';
        $written = Storage::disk('documents')->put($storagePath, $pdf->output());

        if (! $written) {
            $this->fail(new \RuntimeException("Failed to write decision letter PDF to {$storagePath}"));

            return;
        }

        $application->update(['decision_letter_pdf_path' => $storagePath]);
    }
}
```

- [ ] **Step 7: Wire dispatch into ApproveApplication**

In `app/Domain/Applications/Actions/ApproveApplication.php`, add this import at the top:

```php
use App\Domain\Applications\Jobs\GenerateDecisionLetterPdf;
```

Then add this dispatch call at the end of `execute()`, after `$this->notifyApplicant($application)`:

```php
GenerateDecisionLetterPdf::dispatch($application->ulid)->onQueue('pdfs');
```

- [ ] **Step 8: Wire dispatch into RejectApplication**

In `app/Domain/Applications/Actions/RejectApplication.php`, add this import at the top:

```php
use App\Domain\Applications\Jobs\GenerateDecisionLetterPdf;
```

Then add this dispatch call at the end of `execute()`, after the existing `notify()` call:

```php
GenerateDecisionLetterPdf::dispatch($application->ulid)->onQueue('pdfs');
```

- [ ] **Step 9: Run tests**

```bash
php artisan test --compact --filter=GenerateDecisionLetterPdfTest
```

Expected: 3 PASS

Also verify existing approval/rejection tests still pass:

```bash
php artisan test --compact --filter="ApproveApplicationActionTest|RejectApplicationActionTest"
```

Expected: All PASS.

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Applications/Jobs/GenerateDecisionLetterPdf.php \
        resources/views/pdfs/decision-letter.blade.php \
        app/Domain/Applications/Models/VisaApplication.php \
        app/Domain/Applications/Actions/ApproveApplication.php \
        app/Domain/Applications/Actions/RejectApplication.php \
        tests/Feature/GenerateDecisionLetterPdfTest.php \
        database/migrations/*add_decision_letter_pdf_path*
git commit -m "feat(m6): add GenerateDecisionLetterPdf job; dispatch from approve/reject actions (pdfs queue)"
```

---

## Task 6: GenerateAppointmentConfirmationPdf

**Files:**
- Create migration: `add_confirmation_pdf_path_to_application_appointments_table.php`
- Create: `app/Domain/Applications/Jobs/GenerateAppointmentConfirmationPdf.php`
- Create: `resources/views/pdfs/appointment-confirmation.blade.php`
- Modify: `app/Domain/Applications/Models/ApplicationAppointment.php` (add to `$fillable`)
- Modify: `app/Domain/Applications/Actions/ScheduleAppointment.php` (dispatch job)
- Test: `tests/Feature/GenerateAppointmentConfirmationPdfTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\ScheduleAppointment;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Jobs\GenerateAppointmentConfirmationPdf;
use App\Domain\Applications\Models\ApplicationAppointment;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateAppointmentConfirmationPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_appointment_dispatches_confirmation_pdf_job(): void
    {
        Queue::fake();

        $actor = User::factory()->create();
        $application = $this->makeSubmittedApplication();

        (new ScheduleAppointment)->execute($application, $actor, Carbon::now()->addDays(7));

        Queue::assertPushedOn('pdfs', GenerateAppointmentConfirmationPdf::class);
    }

    public function test_confirmation_pdf_job_generates_pdf_and_stores_path(): void
    {
        Storage::fake('documents');

        $application = $this->makeSubmittedApplication();
        $actor = User::factory()->create();
        $appointment = ApplicationAppointment::create([
            'visa_application_id' => $application->ulid,
            'created_by' => $actor->id,
            'appointment_at' => now()->addDays(7),
            'location' => 'Main Office',
            'instructions' => 'Bring originals.',
        ]);

        (new GenerateAppointmentConfirmationPdf($appointment->ulid))->handle();

        $this->assertNotNull($appointment->fresh()->confirmation_pdf_path);
        Storage::disk('documents')->assertExists($appointment->fresh()->confirmation_pdf_path);
    }

    private function makeSubmittedApplication(): VisaApplication
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'AC', 'iso3' => 'ACT']);
        $type = VisaType::create([
            'name' => 'Tourist', 'code' => 'AC_30', 'country_id' => $country->id,
            'processing_days' => 3, 'validity_days' => 30,
        ]);
        $form = FormTemplate::create(['visa_type_id' => $type->ulid, 'name' => 'Form', 'schema' => json_encode([])]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id, 'first_name' => 'Appt', 'last_name' => 'Test',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'AC345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Appt St', 'city' => 'London',
        ]);

        return VisaApplication::create([
            'tracking_number' => 'VA-AC-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --compact --filter=GenerateAppointmentConfirmationPdfTest
```

Expected: FAIL — `GenerateAppointmentConfirmationPdf` not found.

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration add_confirmation_pdf_path_to_application_appointments_table --no-interaction
```

Edit the generated migration:

```php
public function up(): void
{
    Schema::table('application_appointments', function (Blueprint $table) {
        $table->string('confirmation_pdf_path')->nullable()->after('instructions');
    });
}

public function down(): void
{
    Schema::table('application_appointments', function (Blueprint $table) {
        $table->dropColumn('confirmation_pdf_path');
    });
}
```

Run it:

```bash
php artisan migrate --no-interaction
```

- [ ] **Step 4: Update ApplicationAppointment fillable**

In `app/Domain/Applications/Models/ApplicationAppointment.php`, add `'confirmation_pdf_path'` to `$fillable`:

```php
protected $fillable = [
    'visa_application_id',
    'created_by',
    'appointment_at',
    'location',
    'instructions',
    'confirmation_pdf_path',
];
```

- [ ] **Step 5: Create the Blade template**

Create `resources/views/pdfs/appointment-confirmation.blade.php`:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1a1a1a; margin: 40px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .subtitle { color: #6b7280; margin-bottom: 32px; }
        .field { margin-bottom: 12px; }
        .label { color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
        .value { font-size: 14px; margin-top: 2px; }
        .highlight-box { padding: 16px; margin: 24px 0; background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px; }
        .footer { margin-top: 48px; font-size: 11px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 16px; }
    </style>
</head>
<body>
    <h1>Appointment Confirmation</h1>
    <p class="subtitle">Application Ref: {{ $application->tracking_number }}</p>

    <div class="field">
        <div class="label">Applicant</div>
        <div class="value">{{ $applicantProfile->full_name }}</div>
    </div>
    <div class="field">
        <div class="label">Visa Type</div>
        <div class="value">{{ $application->visaType->name }}</div>
    </div>

    <div class="highlight-box">
        <div class="field">
            <div class="label">Appointment Date &amp; Time</div>
            <div class="value" style="font-size: 16px; font-weight: bold;">{{ $appointment->appointment_at->format('l, F j, Y \a\t g:i A') }}</div>
        </div>
        @if($appointment->location)
        <div class="field" style="margin-top: 12px;">
            <div class="label">Location</div>
            <div class="value">{{ $appointment->location }}</div>
        </div>
        @endif
    </div>

    @if($appointment->instructions)
    <div class="field">
        <div class="label">Instructions</div>
        <div class="value">{{ $appointment->instructions }}</div>
    </div>
    @endif

    <div class="footer">
        Please bring this document and a valid photo ID to your appointment. Generated on {{ now()->format('d M Y') }}.
    </div>
</body>
</html>
```

- [ ] **Step 6: Create the job class**

Create `app/Domain/Applications/Jobs/GenerateAppointmentConfirmationPdf.php`:

```php
<?php

namespace App\Domain\Applications\Jobs;

use App\Domain\Applications\Models\ApplicationAppointment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateAppointmentConfirmationPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly string $appointmentUlid) {}

    public function handle(): void
    {
        $appointment = ApplicationAppointment::with([
            'visaApplication.visaType',
            'visaApplication.applicantProfile',
        ])->findOrFail($this->appointmentUlid);

        $application = $appointment->visaApplication;
        $applicantProfile = $application->applicantProfile;

        $pdf = Pdf::loadView('pdfs.appointment-confirmation', compact('appointment', 'application', 'applicantProfile'));

        $storagePath = 'appointments/'.Str::ulid().'.pdf';
        $written = Storage::disk('documents')->put($storagePath, $pdf->output());

        if (! $written) {
            $this->fail(new \RuntimeException("Failed to write appointment confirmation PDF to {$storagePath}"));

            return;
        }

        $appointment->update(['confirmation_pdf_path' => $storagePath]);
    }
}
```

- [ ] **Step 7: Wire dispatch into ScheduleAppointment**

In `app/Domain/Applications/Actions/ScheduleAppointment.php`, add this import:

```php
use App\Domain\Applications\Jobs\GenerateAppointmentConfirmationPdf;
```

Then in `execute()`, after the existing `$applicantUser->notify(...)` call, add:

```php
GenerateAppointmentConfirmationPdf::dispatch($appointment->ulid)->onQueue('pdfs');
```

- [ ] **Step 8: Run tests**

```bash
php artisan test --compact --filter=GenerateAppointmentConfirmationPdfTest
php artisan test --compact --filter=ScheduleAppointmentActionTest
```

Expected: All PASS.

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Applications/Jobs/GenerateAppointmentConfirmationPdf.php \
        resources/views/pdfs/appointment-confirmation.blade.php \
        app/Domain/Applications/Models/ApplicationAppointment.php \
        app/Domain/Applications/Actions/ScheduleAppointment.php \
        tests/Feature/GenerateAppointmentConfirmationPdfTest.php \
        database/migrations/*add_confirmation_pdf_path*
git commit -m "feat(m6): add GenerateAppointmentConfirmationPdf job; dispatch from ScheduleAppointment (pdfs queue)"
```

---

## Task 7: GenerateApplicationSummaryPdf

**Files:**
- Create migration: `add_summary_pdf_path_to_visa_applications_table.php`
- Create: `app/Domain/Applications/Jobs/GenerateApplicationSummaryPdf.php`
- Create: `resources/views/pdfs/application-summary.blade.php`
- Modify: `app/Domain/Applications/Models/VisaApplication.php` (add `summary_pdf_path` to `$fillable`)
- Modify: `app/Domain/Applications/Actions/SubmitApplication.php` (dispatch job)
- Test: `tests/Feature/GenerateApplicationSummaryPdfTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\SubmitApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Jobs\GenerateApplicationSummaryPdf;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateApplicationSummaryPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_application_dispatches_summary_pdf_job(): void
    {
        Queue::fake();

        ['application' => $application] = $this->makeContext();
        $actor = User::factory()->create();

        (new SubmitApplication)->execute($application, $actor);

        Queue::assertPushedOn('pdfs', GenerateApplicationSummaryPdf::class);
    }

    public function test_summary_pdf_job_generates_pdf_and_stores_path(): void
    {
        Storage::fake('documents');

        ['application' => $application] = $this->makeContext();
        $application->update(['status' => ApplicationStatus::Submitted, 'submitted_at' => now()]);

        (new GenerateApplicationSummaryPdf($application->ulid))->handle();

        $this->assertNotNull($application->fresh()->summary_pdf_path);
        Storage::disk('documents')->assertExists($application->fresh()->summary_pdf_path);
    }

    private function makeContext(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'SU', 'iso3' => 'SUM']);
        $type = VisaType::create([
            'name' => 'Tourist', 'code' => 'SUM_30', 'country_id' => $country->id,
            'processing_days' => 3, 'validity_days' => 30,
        ]);
        $form = FormTemplate::create(['visa_type_id' => $type->ulid, 'name' => 'Form', 'schema' => json_encode([])]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id, 'first_name' => 'Summary', 'last_name' => 'Test',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'SU345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Test St', 'city' => 'London',
        ]);
        $application = VisaApplication::create([
            'tracking_number' => 'VA-SUM-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Draft,
        ]);

        return compact('application');
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --compact --filter=GenerateApplicationSummaryPdfTest
```

Expected: FAIL — `GenerateApplicationSummaryPdf` not found.

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration add_summary_pdf_path_to_visa_applications_table --no-interaction
```

Edit the generated migration:

```php
public function up(): void
{
    Schema::table('visa_applications', function (Blueprint $table) {
        $table->string('summary_pdf_path')->nullable()->after('decision_letter_pdf_path');
    });
}

public function down(): void
{
    Schema::table('visa_applications', function (Blueprint $table) {
        $table->dropColumn('summary_pdf_path');
    });
}
```

Run it:

```bash
php artisan migrate --no-interaction
```

- [ ] **Step 4: Update VisaApplication model fillable**

In `app/Domain/Applications/Models/VisaApplication.php`, also add `'summary_pdf_path'` to the `$fillable` array (alongside `decision_letter_pdf_path` from Task 5):

```php
protected $fillable = [
    'tracking_number',
    'applicant_profile_id',
    'visa_type_id',
    'form_template_id',
    'status',
    'assigned_officer_id',
    'submitted_at',
    'travel_date',
    'decision_at',
    'decision_reason',
    'decision_letter_pdf_path',
    'summary_pdf_path',
];
```

- [ ] **Step 5: Create the Blade template**

Create `resources/views/pdfs/application-summary.blade.php`:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1a1a1a; margin: 40px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .subtitle { color: #6b7280; margin-bottom: 32px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; margin-bottom: 24px; }
        th { background: #f3f4f6; text-align: left; padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
        td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
        .footer { margin-top: 48px; font-size: 11px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 16px; }
    </style>
</head>
<body>
    <h1>Application Submission Confirmation</h1>
    <p class="subtitle">Tracking Number: {{ $application->tracking_number }}</p>

    <table>
        <tr><th colspan="2">Applicant Details</th></tr>
        <tr>
            <td>Full Name</td>
            <td>{{ $applicantProfile->full_name }}</td>
        </tr>
        <tr>
            <td>Date of Birth</td>
            <td>{{ $applicantProfile->date_of_birth->format('d M Y') }}</td>
        </tr>
        <tr>
            <td>Passport Number</td>
            <td>{{ $applicantProfile->passport_number }}</td>
        </tr>
    </table>

    <table>
        <tr><th colspan="2">Application Details</th></tr>
        <tr>
            <td>Visa Type</td>
            <td>{{ $application->visaType->name }}</td>
        </tr>
        <tr>
            <td>Submitted On</td>
            <td>{{ $application->submitted_at?->format('d M Y \a\t H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <td>Status</td>
            <td>{{ $application->status->label() }}</td>
        </tr>
    </table>

    <div class="footer">
        Keep this document as proof of your submission. Generated on {{ now()->format('d M Y') }}.
    </div>
</body>
</html>
```

- [ ] **Step 6: Create the job class**

Create `app/Domain/Applications/Jobs/GenerateApplicationSummaryPdf.php`:

```php
<?php

namespace App\Domain\Applications\Jobs;

use App\Domain\Applications\Models\VisaApplication;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateApplicationSummaryPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly string $visaApplicationUlid) {}

    public function handle(): void
    {
        $application = VisaApplication::with(['visaType', 'applicantProfile'])
            ->findOrFail($this->visaApplicationUlid);

        $applicantProfile = $application->applicantProfile;

        $pdf = Pdf::loadView('pdfs.application-summary', compact('application', 'applicantProfile'));

        $storagePath = 'summaries/'.Str::ulid().'.pdf';
        $written = Storage::disk('documents')->put($storagePath, $pdf->output());

        if (! $written) {
            $this->fail(new \RuntimeException("Failed to write application summary PDF to {$storagePath}"));

            return;
        }

        $application->update(['summary_pdf_path' => $storagePath]);
    }
}
```

- [ ] **Step 7: Wire dispatch into SubmitApplication**

In `app/Domain/Applications/Actions/SubmitApplication.php` (which was already modified in Task 1), add this import:

```php
use App\Domain\Applications\Jobs\GenerateApplicationSummaryPdf;
```

Then add this dispatch call at the end of `execute()`, after the notification dispatch:

```php
GenerateApplicationSummaryPdf::dispatch($application->ulid)->onQueue('pdfs');
```

The complete updated `execute()` method will look like:

```php
public function execute(VisaApplication $application, User $actor): VisaApplication
{
    DB::transaction(function () use ($application, $actor) {
        $blockingDocExists = $application->documents()
            ->whereIn('status', [
                DocumentStatus::Pending->value,
                DocumentStatus::Rejected->value,
                DocumentStatus::Infected->value,
            ])
            ->exists();

        if ($blockingDocExists) {
            throw new \RuntimeException('All required documents must be uploaded before submission.');
        }

        $fromStatus = $application->status->value;

        $application->update([
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ]);

        ApplicationStatusHistory::create([
            'visa_application_id' => $application->ulid,
            'from_status' => $fromStatus,
            'to_status' => ApplicationStatus::Submitted->value,
            'actor_id' => $actor->id,
            'created_at' => now(),
        ]);
    });

    $application->refresh();

    $application->load('applicantProfile.user');
    $applicantUser = $application->applicantProfile?->user;

    if ($applicantUser) {
        $applicantUser->notify(new ApplicationSubmittedNotification($application));
    }

    GenerateApplicationSummaryPdf::dispatch($application->ulid)->onQueue('pdfs');

    return $application;
}
```

- [ ] **Step 8: Run tests**

```bash
php artisan test --compact --filter=GenerateApplicationSummaryPdfTest
php artisan test --compact --filter=ApplicationSubmittedNotificationTest
php artisan test --compact --filter=SubmitApplicationDocumentCheckTest
```

Expected: All PASS.

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Applications/Jobs/GenerateApplicationSummaryPdf.php \
        resources/views/pdfs/application-summary.blade.php \
        app/Domain/Applications/Models/VisaApplication.php \
        app/Domain/Applications/Actions/SubmitApplication.php \
        tests/Feature/GenerateApplicationSummaryPdfTest.php \
        database/migrations/*add_summary_pdf_path*
git commit -m "feat(m6): add GenerateApplicationSummaryPdf job; dispatch on submission (pdfs queue)"
```

---

## Task 8: Retry Strategy & Full Test Suite

**Goal:** Add `$tries`, `$timeout`, `$backoff` to all existing ShouldQueue jobs and notifications that are missing them, then run the complete test suite.

**Files modified:**
- `app/Domain/Payments/Jobs/GenerateReceiptPdf.php`
- `app/Notifications/ApplicationApprovedNotification.php`
- `app/Notifications/ApplicationRejectedNotification.php`
- `app/Notifications/AdditionalInfoRequestedNotification.php`
- `app/Notifications/AppointmentScheduledNotification.php`

- [ ] **Step 1: Add retry strategy to GenerateReceiptPdf**

In `app/Domain/Payments/Jobs/GenerateReceiptPdf.php`, add these properties immediately after `use Queueable;`:

```php
public int $tries = 3;
public int $timeout = 60;
public array $backoff = [10, 30, 60];
```

- [ ] **Step 2: Add retry strategy to ApplicationApprovedNotification**

In `app/Notifications/ApplicationApprovedNotification.php`, add these properties immediately after `use Queueable;`:

```php
public int $tries = 5;
public int $timeout = 30;
public array $backoff = [5, 15, 30];
```

- [ ] **Step 3: Add retry strategy to ApplicationRejectedNotification**

In `app/Notifications/ApplicationRejectedNotification.php`, add the same properties:

```php
public int $tries = 5;
public int $timeout = 30;
public array $backoff = [5, 15, 30];
```

- [ ] **Step 4: Add retry strategy to AdditionalInfoRequestedNotification**

In `app/Notifications/AdditionalInfoRequestedNotification.php`, add the same properties:

```php
public int $tries = 5;
public int $timeout = 30;
public array $backoff = [5, 15, 30];
```

- [ ] **Step 5: Add retry strategy to AppointmentScheduledNotification**

In `app/Notifications/AppointmentScheduledNotification.php`, add the same properties:

```php
public int $tries = 5;
public int $timeout = 30;
public array $backoff = [5, 15, 30];
```

- [ ] **Step 6: Run full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass (should be 107 original + new tests = 125+ passing). Zero failures.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Payments/Jobs/GenerateReceiptPdf.php \
        app/Notifications/ApplicationApprovedNotification.php \
        app/Notifications/ApplicationRejectedNotification.php \
        app/Notifications/AdditionalInfoRequestedNotification.php \
        app/Notifications/AppointmentScheduledNotification.php
git commit -m "feat(m6): add retry strategy (tries/timeout/backoff) to all ShouldQueue jobs and notifications"
```

---

## Spec Coverage Check

| Acceptance Criterion | Covered By |
|---|---|
| Application submission dispatches notification job | Task 1 (ApplicationSubmittedNotification on `high`) |
| Application submission dispatches PDF job | Task 7 (GenerateApplicationSummaryPdf on `pdfs`) |
| Payment success dispatches receipt PDF job | Already done (GenerateReceiptPdf exists) |
| Payment success dispatches notification job | Task 2 (PaymentSucceededNotification on `default`) |
| Document rejection dispatches applicant notification | Task 3 (DocumentRejectedNotification on `emails`) |
| Failed jobs are visible in Horizon and retryable | Task 8 (retry strategy on all jobs) |
| No slow work blocks HTTP requests | All notifications/jobs implement ShouldQueue |
| All 6 queue names in active use | `high`: Task 1; `default`: Task 2; `emails`: Task 3; `documents`: Task 4; `pdfs`: Tasks 5–7; `reports`: ExportApplicationsJob (pre-existing) |
| Queued PDF jobs: receipt | Pre-existing |
| Queued PDF jobs: appointment confirmation | Task 6 |
| Queued PDF jobs: decision letter | Task 5 |
| Queued PDF jobs: application summary | Task 7 |
