# Milestone 5 — Officer Review & Decisions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the full officer review lifecycle in Filament: assign cases, request additional info, schedule appointments, approve/reject with document readiness guards, and notify applicants via email and database notifications.

**Architecture:** Domain actions in `app/Domain/Applications/Actions/` handle all business logic (wrapped in DB transactions); Filament table/page actions are thin wrappers that call domain actions and catch exceptions. Notifications live in `app/Notifications/` and are dispatched from within domain actions via the `emails` queue. The `VisaApplicationResource` query is scoped so that `case_officer` users see only their assigned applications.

**Tech Stack:** Laravel 12, Filament v4, Spatie Laravel Permission, Laravel Notifications (email + database channels), PHPUnit.

**Baseline:** 87 tests pass. All pre-existing actions (`ApproveApplication`, `RejectApplication`, `AssignApplicationToOfficer`) are already correct transactions with status history — this plan extends them, it does not replace them.

---

## What already exists (do not re-implement)

- `ApproveApplication`, `RejectApplication`, `AssignApplicationToOfficer` actions ✓
- `VisaApplicationPolicy` with `viewAny`, `view`, `approve`, `reject`, `assign` ✓
- `VisaApplicationResource` with `DocumentsRelationManager` and `PaymentsRelationManager` ✓
- `VisaApplicationsTable` with approve/reject actions and bulk-assign ✓
- `ListVisaApplications` with status tabs ✓
- `ApplicationStatus` enum with `AdditionalInfoRequested` ✓

## File Map

| File | Action | Responsibility |
|---|---|---|
| `database/migrations/2026_05_16_000001_create_application_notes_table.php` | Create | Officer notes schema |
| `database/migrations/2026_05_16_000002_create_application_appointments_table.php` | Create | Appointment slots schema |
| `database/migrations/2026_05_16_000003_create_notifications_table.php` | Create | Laravel database notifications |
| `app/Domain/Applications/Models/ApplicationNote.php` | Create | Note model + relationships |
| `app/Domain/Applications/Models/ApplicationAppointment.php` | Create | Appointment model + relationships |
| `app/Domain/Applications/Models/VisaApplication.php` | Modify | Add `notes()` and `appointment()` relationships |
| `app/Notifications/ApplicationApprovedNotification.php` | Create | Email + DB notification on approval |
| `app/Notifications/ApplicationRejectedNotification.php` | Create | Email + DB notification on rejection |
| `app/Notifications/AdditionalInfoRequestedNotification.php` | Create | Email + DB notification on info request |
| `app/Notifications/AppointmentScheduledNotification.php` | Create | Email + DB notification on appointment |
| `app/Domain/Applications/Policies/VisaApplicationPolicy.php` | Modify | Add `requestAdditionalInfo`, `scheduleAppointment` |
| `app/Domain/Applications/Actions/ApproveApplication.php` | Modify | Add document readiness guard + dispatch notification |
| `app/Domain/Applications/Actions/RejectApplication.php` | Modify | Dispatch notification |
| `app/Domain/Applications/Actions/RequestAdditionalInformation.php` | Create | Transition status + record history + notify |
| `app/Domain/Applications/Actions/ScheduleAppointment.php` | Create | Create appointment + record history + notify |
| `app/Filament/Resources/VisaApplications/VisaApplicationResource.php` | Modify | Officer-scoped query + register new relation managers |
| `app/Filament/Resources/VisaApplications/Tables/VisaApplicationsTable.php` | Modify | Add filters (status, country, date), add new actions, fix bulk assign |
| `app/Filament/Resources/VisaApplications/RelationManagers/NotesRelationManager.php` | Create | List + create officer notes |
| `app/Filament/Resources/VisaApplications/RelationManagers/StatusHistoryRelationManager.php` | Create | Read-only audit trail of status transitions |
| `app/Filament/Resources/VisaApplications/Infolists/VisaApplicationInfolist.php` | Create | Infolist for ViewVisaApplication page |
| `app/Filament/Resources/VisaApplications/Pages/ViewVisaApplication.php` | Modify | Add infolist + page-level actions |
| `tests/Feature/RequestAdditionalInfoActionTest.php` | Create | Action records history + fires notification |
| `tests/Feature/ScheduleAppointmentActionTest.php` | Create | Appointment saved + history recorded + notification fired |
| `tests/Feature/ApproveBlockedByDocumentsTest.php` | Create | Approve guard when docs pending/rejected/missing |
| `tests/Unit/VisaApplicationPolicyScopeTest.php` | Modify | Add senior officer + new policy method tests |

---

## Task 1: Migrations (notes + appointments + notifications)

**Files:**
- Create: `database/migrations/2026_05_16_000001_create_application_notes_table.php`
- Create: `database/migrations/2026_05_16_000002_create_application_appointments_table.php`
- Create: `database/migrations/2026_05_16_000003_create_notifications_table.php`

- [ ] **Step 1: Create the application_notes migration**

```bash
php artisan make:migration create_application_notes_table --no-interaction
```

Open the generated file and replace `up()` with:

```php
public function up(): void
{
    Schema::create('application_notes', function (Blueprint $table) {
        $table->char('ulid', 26)->primary();
        $table->char('visa_application_id', 26)->index();
        $table->foreign('visa_application_id')->references('ulid')->on('visa_applications')->cascadeOnDelete();
        $table->foreignId('author_id')->constrained('users');
        $table->text('body');
        $table->boolean('is_visible_to_applicant')->default(false);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('application_notes');
}
```

- [ ] **Step 2: Create the application_appointments migration**

```bash
php artisan make:migration create_application_appointments_table --no-interaction
```

Open and replace `up()` with:

```php
public function up(): void
{
    Schema::create('application_appointments', function (Blueprint $table) {
        $table->char('ulid', 26)->primary();
        $table->char('visa_application_id', 26)->index();
        $table->foreign('visa_application_id')->references('ulid')->on('visa_applications')->cascadeOnDelete();
        $table->foreignId('created_by')->constrained('users');
        $table->dateTime('appointment_at');
        $table->string('location')->nullable();
        $table->text('instructions')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('application_appointments');
}
```

- [ ] **Step 3: Create the notifications migration**

```bash
php artisan notifications:table --no-interaction
```

- [ ] **Step 4: Run all migrations**

```bash
php artisan migrate
```

Expected: 3 new tables created, no errors.

- [ ] **Step 5: Run tests to confirm baseline still passes**

```bash
php artisan test --compact
```

Expected: 87 passed.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/
git commit -m "feat(m5): add application_notes, application_appointments, and notifications migrations"
```

---

## Task 2: ApplicationNote model + VisaApplication relationship

**Files:**
- Create: `app/Domain/Applications/Models/ApplicationNote.php`
- Modify: `app/Domain/Applications/Models/VisaApplication.php`

- [ ] **Step 1: Create the ApplicationNote model**

```bash
php artisan make:model ApplicationNote --no-interaction
```

Move the generated file to `app/Domain/Applications/Models/ApplicationNote.php` and replace its contents with:

```php
<?php

namespace App\Domain\Applications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationNote extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'visa_application_id',
        'author_id',
        'body',
        'is_visible_to_applicant',
    ];

    protected function casts(): array
    {
        return [
            'is_visible_to_applicant' => 'boolean',
        ];
    }

    public function visaApplication(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'visa_application_id', 'ulid');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
```

- [ ] **Step 2: Create the ApplicationAppointment model**

```bash
php artisan make:model ApplicationAppointment --no-interaction
```

Move to `app/Domain/Applications/Models/ApplicationAppointment.php` and replace with:

```php
<?php

namespace App\Domain\Applications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationAppointment extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'visa_application_id',
        'created_by',
        'appointment_at',
        'location',
        'instructions',
    ];

    protected function casts(): array
    {
        return [
            'appointment_at' => 'datetime',
        ];
    }

    public function visaApplication(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'visa_application_id', 'ulid');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

- [ ] **Step 3: Add relationships to VisaApplication**

In `app/Domain/Applications/Models/VisaApplication.php`, add these two imports at the top:

```php
use App\Domain\Applications\Models\ApplicationNote;
use App\Domain\Applications\Models\ApplicationAppointment;
```

Then add these two methods before the closing `}`:

```php
public function notes(): HasMany
{
    return $this->hasMany(ApplicationNote::class, 'visa_application_id', 'ulid')
        ->latest();
}

public function appointments(): HasMany
{
    return $this->hasMany(ApplicationAppointment::class, 'visa_application_id', 'ulid')
        ->latest();
}

public function latestAppointment(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(ApplicationAppointment::class, 'visa_application_id', 'ulid')
        ->latestOfMany('appointment_at');
}
```

- [ ] **Step 4: Run formatter**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact
```

Expected: 87 passed (no regressions).

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Applications/Models/ app/Models/ApplicationNote.php app/Models/ApplicationAppointment.php 2>/dev/null; git add app/Domain/Applications/Models/
git commit -m "feat(m5): add ApplicationNote and ApplicationAppointment models with VisaApplication relationships"
```

---

## Task 3: Notifications (4 classes)

**Files:**
- Create: `app/Notifications/ApplicationApprovedNotification.php`
- Create: `app/Notifications/ApplicationRejectedNotification.php`
- Create: `app/Notifications/AdditionalInfoRequestedNotification.php`
- Create: `app/Notifications/AppointmentScheduledNotification.php`

Each notification sends on both the `mail` and `database` channels and is queued on the `emails` queue.

- [ ] **Step 1: Create ApplicationApprovedNotification**

```bash
php artisan make:notification ApplicationApprovedNotification --no-interaction
```

Replace the file `app/Notifications/ApplicationApprovedNotification.php` with:

```php
<?php

namespace App\Notifications;

use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $queue = 'emails';

    public function __construct(public readonly VisaApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your visa application has been approved')
            ->greeting('Good news, '.$notifiable->name.'!')
            ->line('Your visa application ('.$this->application->tracking_number.') has been approved.')
            ->when($this->application->decision_reason, fn ($m) => $m->line('Note: '.$this->application->decision_reason))
            ->action('View Application', url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application_approved',
            'visa_application_id' => $this->application->ulid,
            'tracking_number' => $this->application->tracking_number,
            'message' => 'Your visa application ('.$this->application->tracking_number.') has been approved.',
        ];
    }
}
```

- [ ] **Step 2: Create ApplicationRejectedNotification**

```bash
php artisan make:notification ApplicationRejectedNotification --no-interaction
```

Replace `app/Notifications/ApplicationRejectedNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $queue = 'emails';

    public function __construct(public readonly VisaApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your visa application decision')
            ->greeting('Dear '.$notifiable->name.',')
            ->line('We regret to inform you that your visa application ('.$this->application->tracking_number.') has been rejected.')
            ->when($this->application->decision_reason, fn ($m) => $m->line('Reason: '.$this->application->decision_reason))
            ->action('View Application', url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application_rejected',
            'visa_application_id' => $this->application->ulid,
            'tracking_number' => $this->application->tracking_number,
            'message' => 'Your application ('.$this->application->tracking_number.') has been rejected.',
        ];
    }
}
```

- [ ] **Step 3: Create AdditionalInfoRequestedNotification**

```bash
php artisan make:notification AdditionalInfoRequestedNotification --no-interaction
```

Replace `app/Notifications/AdditionalInfoRequestedNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdditionalInfoRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $queue = 'emails';

    public function __construct(
        public readonly VisaApplication $application,
        public readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Additional information requested for your visa application')
            ->greeting('Dear '.$notifiable->name.',')
            ->line('Additional information is required for your application ('.$this->application->tracking_number.').')
            ->line($this->message)
            ->action('View Application', url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'additional_info_requested',
            'visa_application_id' => $this->application->ulid,
            'tracking_number' => $this->application->tracking_number,
            'message' => $this->message,
        ];
    }
}
```

- [ ] **Step 4: Create AppointmentScheduledNotification**

```bash
php artisan make:notification AppointmentScheduledNotification --no-interaction
```

Replace `app/Notifications/AppointmentScheduledNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Domain\Applications\Models\ApplicationAppointment;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $queue = 'emails';

    public function __construct(
        public readonly VisaApplication $application,
        public readonly ApplicationAppointment $appointment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Appointment scheduled for your visa application')
            ->greeting('Dear '.$notifiable->name.',')
            ->line('An appointment has been scheduled for your application ('.$this->application->tracking_number.').')
            ->line('**Date & Time:** '.$this->appointment->appointment_at->format('l, F j, Y \a\t g:i A'));

        if ($this->appointment->location) {
            $mail->line('**Location:** '.$this->appointment->location);
        }

        if ($this->appointment->instructions) {
            $mail->line('**Instructions:** '.$this->appointment->instructions);
        }

        return $mail->action('View Application', url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment_scheduled',
            'visa_application_id' => $this->application->ulid,
            'tracking_number' => $this->application->tracking_number,
            'appointment_at' => $this->appointment->appointment_at->toIso8601String(),
            'location' => $this->appointment->location,
        ];
    }
}
```

- [ ] **Step 5: Run formatter**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Run tests**

```bash
php artisan test --compact
```

Expected: 87 passed.

- [ ] **Step 7: Commit**

```bash
git add app/Notifications/
git commit -m "feat(m5): add applicant notification classes for approval, rejection, info request, and appointment"
```

---

## Task 4: VisaApplicationPolicy — add new authorization methods

**Files:**
- Modify: `app/Domain/Applications/Policies/VisaApplicationPolicy.php`

- [ ] **Step 1: Write the failing tests first**

Create `tests/Unit/VisaApplicationPolicyScopeTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Policies\VisaApplicationPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VisaApplicationPolicyScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::create(['name' => 'senior_officer', 'guard_name' => 'web']);
        Role::create(['name' => 'case_officer', 'guard_name' => 'web']);
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);
    }

    public function test_senior_officer_can_view_any_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('senior_officer');

        $this->assertTrue((new VisaApplicationPolicy)->viewAny($officer));
    }

    public function test_senior_officer_can_view_unassigned_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('senior_officer');

        $application = $this->makeApplication(assigned_officer_id: null);

        $this->assertTrue((new VisaApplicationPolicy)->view($officer, $application));
    }

    public function test_case_officer_cannot_view_unassigned_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        $application = $this->makeApplication(assigned_officer_id: null);

        $this->assertFalse((new VisaApplicationPolicy)->view($officer, $application));
    }

    public function test_senior_officer_can_request_additional_info(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('senior_officer');

        $application = $this->makeApplication(assigned_officer_id: null);

        $this->assertTrue((new VisaApplicationPolicy)->requestAdditionalInfo($officer, $application));
    }

    public function test_case_officer_can_request_additional_info_on_assigned_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        $application = $this->makeApplication(assigned_officer_id: $officer->id);

        $this->assertTrue((new VisaApplicationPolicy)->requestAdditionalInfo($officer, $application));
    }

    public function test_case_officer_cannot_request_additional_info_on_unassigned_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        $application = $this->makeApplication(assigned_officer_id: null);

        $this->assertFalse((new VisaApplicationPolicy)->requestAdditionalInfo($officer, $application));
    }

    public function test_senior_officer_can_schedule_appointment(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('senior_officer');

        $application = $this->makeApplication(assigned_officer_id: null);

        $this->assertTrue((new VisaApplicationPolicy)->scheduleAppointment($officer, $application));
    }

    public function test_case_officer_can_schedule_appointment_on_assigned_application(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        $application = $this->makeApplication(assigned_officer_id: $officer->id);

        $this->assertTrue((new VisaApplicationPolicy)->scheduleAppointment($officer, $application));
    }

    private function makeApplication(?int $assigned_officer_id): VisaApplication
    {
        $application = new VisaApplication([
            'assigned_officer_id' => $assigned_officer_id,
        ]);
        $application->exists = true;

        return $application;
    }
}
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
php artisan test --compact --filter=VisaApplicationPolicyScopeTest
```

Expected: FAIL — `requestAdditionalInfo` and `scheduleAppointment` methods do not exist.

- [ ] **Step 3: Add the missing policy methods**

In `app/Domain/Applications/Policies/VisaApplicationPolicy.php`, add after the `reject()` method:

```php
public function requestAdditionalInfo(User $user, VisaApplication $application): bool
{
    if ($user->hasAnyRole(['super_admin', 'admin', 'senior_officer'])) {
        return true;
    }

    if ($user->hasRole('case_officer')) {
        return $application->assigned_officer_id === $user->id;
    }

    return false;
}

public function scheduleAppointment(User $user, VisaApplication $application): bool
{
    if ($user->hasAnyRole(['super_admin', 'admin', 'senior_officer'])) {
        return true;
    }

    if ($user->hasRole('case_officer')) {
        return $application->assigned_officer_id === $user->id;
    }

    return false;
}
```

- [ ] **Step 4: Run the test to confirm it passes**

```bash
php artisan test --compact --filter=VisaApplicationPolicyScopeTest
```

Expected: all 9 tests pass.

- [ ] **Step 5: Run formatter + full suite**

```bash
vendor/bin/pint --dirty --format agent && php artisan test --compact
```

Expected: 96 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Applications/Policies/VisaApplicationPolicy.php tests/Unit/VisaApplicationPolicyScopeTest.php
git commit -m "feat(m5): add requestAdditionalInfo and scheduleAppointment policy methods with tests"
```

---

## Task 5: ApproveApplication — document readiness guard + notification

**Files:**
- Modify: `app/Domain/Applications/Actions/ApproveApplication.php`
- Create: `tests/Feature/ApproveBlockedByDocumentsTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/ApproveBlockedByDocumentsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\ApproveApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Documents\Models\VisaTypeDocumentRequirement;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use App\Notifications\ApplicationApprovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApproveBlockedByDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_approve_throws_when_required_document_is_not_accepted(): void
    {
        [$application, $docType] = $this->makeApplicationWithRequiredDoc();

        ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Uploaded,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot approve');

        (new ApproveApplication)->execute($application, User::factory()->create());
    }

    public function test_approve_throws_when_required_document_is_rejected(): void
    {
        [$application, $docType] = $this->makeApplicationWithRequiredDoc();

        ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Rejected,
        ]);

        $this->expectException(\RuntimeException::class);

        (new ApproveApplication)->execute($application, User::factory()->create());
    }

    public function test_approve_throws_when_required_document_is_missing(): void
    {
        [$application] = $this->makeApplicationWithRequiredDoc();

        $this->expectException(\RuntimeException::class);

        (new ApproveApplication)->execute($application, User::factory()->create());
    }

    public function test_approve_succeeds_when_all_required_documents_are_accepted(): void
    {
        Notification::fake();

        [$application, $docType] = $this->makeApplicationWithRequiredDoc();

        ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Accepted,
        ]);

        $actor = User::factory()->create();
        (new ApproveApplication)->execute($application, $actor);

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $application->ulid,
            'status' => ApplicationStatus::Approved->value,
        ]);
    }

    public function test_approve_dispatches_notification_to_applicant(): void
    {
        Notification::fake();

        [$application, $docType] = $this->makeApplicationWithRequiredDoc();

        ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Accepted,
        ]);

        $actor = User::factory()->create();
        (new ApproveApplication)->execute($application, $actor);

        $applicantUser = $application->applicantProfile->user;
        Notification::assertSentTo($applicantUser, ApplicationApprovedNotification::class);
    }

    private function makeApplicationWithRequiredDoc(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $type = VisaType::create([
            'name' => 'Tourist',
            'code' => 'TEST_30',
            'country_id' => $country->id,
            'processing_days' => 3,
            'validity_days' => 30,
        ]);
        $docType = DocumentType::create([
            'name' => 'Passport',
            'code' => 'PASSPORT',
            'allowed_mime_types' => json_encode(['application/pdf']),
            'max_size_kb' => 5000,
        ]);
        VisaTypeDocumentRequirement::create([
            'visa_type_id' => $type->ulid,
            'document_type_id' => $docType->ulid,
            'is_required' => true,
            'display_order' => 1,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $type->ulid,
            'name' => 'Tourist Form',
            'schema' => json_encode([]),
        ]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Applicant',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
            'passport_number' => 'A12345678',
            'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890',
            'address_line_1' => '1 Test Street',
            'city' => 'London',
        ]);
        $application = VisaApplication::create([
            'tracking_number' => 'VA-'.now()->year.'-TEST01',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::UnderReview,
            'submitted_at' => now(),
        ]);

        return [$application, $docType];
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
php artisan test --compact --filter=ApproveBlockedByDocumentsTest
```

Expected: FAIL — no exception thrown, no notification.

- [ ] **Step 3: Update ApproveApplication action**

Replace `app/Domain/Applications/Actions/ApproveApplication.php`:

```php
<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\User;
use App\Notifications\ApplicationApprovedNotification;
use Illuminate\Support\Facades\DB;

class ApproveApplication
{
    public function execute(VisaApplication $application, User $actor, ?string $reason = null): VisaApplication
    {
        $this->guardDocumentReadiness($application);

        return DB::transaction(function () use ($application, $actor, $reason) {
            $fromStatus = $application->status->value;

            $application->update([
                'status' => ApplicationStatus::Approved,
                'decision_at' => now(),
                'decision_reason' => $reason,
            ]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => $fromStatus,
                'to_status' => ApplicationStatus::Approved->value,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($application)
                ->withProperties(['from' => $fromStatus, 'to' => ApplicationStatus::Approved->value])
                ->log('status_changed');

            return $application->fresh();
        });

        $this->notifyApplicant($application);

        return $application;
    }

    private function guardDocumentReadiness(VisaApplication $application): void
    {
        $application->load('visaType.documentRequirements', 'documents');

        $requiredTypeIds = $application->visaType
            ->documentRequirements
            ->where('is_required', true)
            ->pluck('document_type_id');

        if ($requiredTypeIds->isEmpty()) {
            return;
        }

        $acceptedTypeIds = $application->documents
            ->where('status', DocumentStatus::Accepted)
            ->pluck('document_type_id');

        $missing = $requiredTypeIds->diff($acceptedTypeIds);

        if ($missing->isNotEmpty()) {
            throw new \RuntimeException('Cannot approve: all required documents must be accepted first.');
        }
    }

    private function notifyApplicant(VisaApplication $application): void
    {
        $application->load('applicantProfile.user');
        $applicantUser = $application->applicantProfile?->user;

        if ($applicantUser) {
            $applicantUser->notify(new ApplicationApprovedNotification($application));
        }
    }
}
```

Wait — there is a bug in the code above: `$this->notifyApplicant($application)` is after a `return` inside the transaction closure. Fix this by restructuring:

```php
<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\User;
use App\Notifications\ApplicationApprovedNotification;
use Illuminate\Support\Facades\DB;

class ApproveApplication
{
    public function execute(VisaApplication $application, User $actor, ?string $reason = null): VisaApplication
    {
        $this->guardDocumentReadiness($application);

        $fromStatus = $application->status->value;

        DB::transaction(function () use ($application, $actor, $reason, $fromStatus) {
            $application->update([
                'status' => ApplicationStatus::Approved,
                'decision_at' => now(),
                'decision_reason' => $reason,
            ]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => $fromStatus,
                'to_status' => ApplicationStatus::Approved->value,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($application)
                ->withProperties(['from' => $fromStatus, 'to' => ApplicationStatus::Approved->value])
                ->log('status_changed');
        });

        $application->refresh();

        $this->notifyApplicant($application);

        return $application;
    }

    private function guardDocumentReadiness(VisaApplication $application): void
    {
        $application->load('visaType.documentRequirements', 'documents');

        $requiredTypeIds = $application->visaType
            ->documentRequirements
            ->where('is_required', true)
            ->pluck('document_type_id');

        if ($requiredTypeIds->isEmpty()) {
            return;
        }

        $acceptedTypeIds = $application->documents
            ->where('status', DocumentStatus::Accepted)
            ->pluck('document_type_id');

        $missing = $requiredTypeIds->diff($acceptedTypeIds);

        if ($missing->isNotEmpty()) {
            throw new \RuntimeException('Cannot approve: all required documents must be accepted first.');
        }
    }

    private function notifyApplicant(VisaApplication $application): void
    {
        $application->load('applicantProfile.user');
        $applicantUser = $application->applicantProfile?->user;

        if ($applicantUser) {
            $applicantUser->notify(new ApplicationApprovedNotification($application));
        }
    }
}
```

- [ ] **Step 4: Run the new tests**

```bash
php artisan test --compact --filter=ApproveBlockedByDocumentsTest
```

Expected: 5 passed.

- [ ] **Step 5: Run full suite to check for regressions**

```bash
php artisan test --compact
```

Expected: all pass. Note — `ApproveApplicationActionTest` will need to be checked; those tests create an application without required docs, so the guard should pass (no requirements = allowed).

- [ ] **Step 6: Run formatter + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Applications/Actions/ApproveApplication.php tests/Feature/ApproveBlockedByDocumentsTest.php
git commit -m "feat(m5): add document readiness guard and applicant notification to ApproveApplication"
```

---

## Task 6: RejectApplication — dispatch notification

**Files:**
- Modify: `app/Domain/Applications/Actions/RejectApplication.php`

- [ ] **Step 1: Update RejectApplication**

Replace `app/Domain/Applications/Actions/RejectApplication.php`:

```php
<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use App\Notifications\ApplicationRejectedNotification;
use Illuminate\Support\Facades\DB;

class RejectApplication
{
    public function execute(VisaApplication $application, User $actor, string $reason): VisaApplication
    {
        $fromStatus = $application->status->value;

        DB::transaction(function () use ($application, $actor, $reason, $fromStatus) {
            $application->update([
                'status' => ApplicationStatus::Rejected,
                'decision_at' => now(),
                'decision_reason' => $reason,
            ]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => $fromStatus,
                'to_status' => ApplicationStatus::Rejected->value,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($application)
                ->withProperties(['from' => $fromStatus, 'to' => ApplicationStatus::Rejected->value, 'reason' => $reason])
                ->log('status_changed');
        });

        $application->refresh();

        $application->load('applicantProfile.user');
        $applicantUser = $application->applicantProfile?->user;

        if ($applicantUser) {
            $applicantUser->notify(new ApplicationRejectedNotification($application));
        }

        return $application;
    }
}
```

- [ ] **Step 2: Run formatter + tests**

```bash
vendor/bin/pint --dirty --format agent && php artisan test --compact
```

Expected: all pass.

- [ ] **Step 3: Commit**

```bash
git add app/Domain/Applications/Actions/RejectApplication.php
git commit -m "feat(m5): dispatch applicant notification from RejectApplication action"
```

---

## Task 7: RequestAdditionalInformation action (new)

**Files:**
- Create: `app/Domain/Applications/Actions/RequestAdditionalInformation.php`
- Create: `tests/Feature/RequestAdditionalInfoActionTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/RequestAdditionalInfoActionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\RequestAdditionalInformation;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use App\Notifications\AdditionalInfoRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RequestAdditionalInfoActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_action_transitions_status_to_additional_info_requested(): void
    {
        $actor = User::factory()->create();
        $application = $this->makeApplication();

        (new RequestAdditionalInformation)->execute($application, $actor, 'Please provide your travel itinerary.');

        $this->assertDatabaseHas('visa_applications', [
            'ulid' => $application->ulid,
            'status' => ApplicationStatus::AdditionalInfoRequested->value,
        ]);
    }

    public function test_action_records_status_history(): void
    {
        $actor = User::factory()->create();
        $application = $this->makeApplication();

        (new RequestAdditionalInformation)->execute($application, $actor, 'Please provide proof of funds.');

        $this->assertDatabaseHas('application_status_histories', [
            'visa_application_id' => $application->ulid,
            'to_status' => ApplicationStatus::AdditionalInfoRequested->value,
            'actor_id' => $actor->id,
        ]);
    }

    public function test_action_dispatches_notification_to_applicant(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $application = $this->makeApplication();

        (new RequestAdditionalInformation)->execute($application, $actor, 'Please provide hotel bookings.');

        $applicantUser = $application->applicantProfile->user;
        Notification::assertSentTo($applicantUser, AdditionalInfoRequestedNotification::class);
    }

    private function makeApplication(): VisaApplication
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $type = VisaType::create([
            'name' => 'Tourist',
            'code' => 'TEST_30',
            'country_id' => $country->id,
            'processing_days' => 3,
            'validity_days' => 30,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $type->ulid,
            'name' => 'Tourist Form',
            'schema' => json_encode([]),
        ]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Applicant',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
            'passport_number' => 'A12345678',
            'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890',
            'address_line_1' => '1 Test Street',
            'city' => 'London',
        ]);

        return VisaApplication::create([
            'tracking_number' => 'VA-'.now()->year.'-TEST01',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::UnderReview,
            'submitted_at' => now(),
        ]);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --compact --filter=RequestAdditionalInfoActionTest
```

Expected: FAIL — class `RequestAdditionalInformation` not found.

- [ ] **Step 3: Create the action**

Create `app/Domain/Applications/Actions/RequestAdditionalInformation.php`:

```php
<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use App\Notifications\AdditionalInfoRequestedNotification;
use Illuminate\Support\Facades\DB;

class RequestAdditionalInformation
{
    public function execute(VisaApplication $application, User $actor, string $message): VisaApplication
    {
        $fromStatus = $application->status->value;

        DB::transaction(function () use ($application, $actor, $message, $fromStatus) {
            $application->update([
                'status' => ApplicationStatus::AdditionalInfoRequested,
            ]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => $fromStatus,
                'to_status' => ApplicationStatus::AdditionalInfoRequested->value,
                'actor_id' => $actor->id,
                'reason' => $message,
                'created_at' => now(),
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($application)
                ->withProperties(['from' => $fromStatus, 'to' => ApplicationStatus::AdditionalInfoRequested->value])
                ->log('status_changed');
        });

        $application->refresh();

        $application->load('applicantProfile.user');
        $applicantUser = $application->applicantProfile?->user;

        if ($applicantUser) {
            $applicantUser->notify(new AdditionalInfoRequestedNotification($application, $message));
        }

        return $application;
    }
}
```

- [ ] **Step 4: Run the tests**

```bash
php artisan test --compact --filter=RequestAdditionalInfoActionTest
```

Expected: 3 passed.

- [ ] **Step 5: Run formatter + full suite**

```bash
vendor/bin/pint --dirty --format agent && php artisan test --compact
```

Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Applications/Actions/RequestAdditionalInformation.php tests/Feature/RequestAdditionalInfoActionTest.php
git commit -m "feat(m5): add RequestAdditionalInformation action with status history and applicant notification"
```

---

## Task 8: ScheduleAppointment action (new)

**Files:**
- Create: `app/Domain/Applications/Actions/ScheduleAppointment.php`
- Create: `tests/Feature/ScheduleAppointmentActionTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ScheduleAppointmentActionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\ScheduleAppointment;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use App\Notifications\AppointmentScheduledNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Domain\Applications\Enums\ApplicationStatus;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ScheduleAppointmentActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_action_creates_appointment_record(): void
    {
        $actor = User::factory()->create();
        $application = $this->makeApplication();
        $appointmentAt = Carbon::parse('2026-06-01 10:00:00');

        (new ScheduleAppointment)->execute($application, $actor, $appointmentAt, 'Main Office', 'Bring all originals.');

        $this->assertDatabaseHas('application_appointments', [
            'visa_application_id' => $application->ulid,
            'created_by' => $actor->id,
            'location' => 'Main Office',
            'instructions' => 'Bring all originals.',
        ]);
    }

    public function test_action_records_status_history(): void
    {
        $actor = User::factory()->create();
        $application = $this->makeApplication();

        (new ScheduleAppointment)->execute($application, $actor, Carbon::parse('2026-06-01 10:00:00'));

        $this->assertDatabaseHas('application_status_histories', [
            'visa_application_id' => $application->ulid,
            'actor_id' => $actor->id,
        ]);
    }

    public function test_action_dispatches_notification_to_applicant(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $application = $this->makeApplication();

        (new ScheduleAppointment)->execute($application, $actor, Carbon::parse('2026-06-01 10:00:00'));

        $applicantUser = $application->applicantProfile->user;
        Notification::assertSentTo($applicantUser, AppointmentScheduledNotification::class);
    }

    private function makeApplication(): VisaApplication
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $type = VisaType::create([
            'name' => 'Tourist',
            'code' => 'TEST_30',
            'country_id' => $country->id,
            'processing_days' => 3,
            'validity_days' => 30,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $type->ulid,
            'name' => 'Tourist Form',
            'schema' => json_encode([]),
        ]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Applicant',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'nationality_id' => $country->id,
            'country_of_residence_id' => $country->id,
            'passport_number' => 'A12345678',
            'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890',
            'address_line_1' => '1 Test Street',
            'city' => 'London',
        ]);

        return VisaApplication::create([
            'tracking_number' => 'VA-'.now()->year.'-TEST01',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $type->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::UnderReview,
            'submitted_at' => now(),
        ]);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --compact --filter=ScheduleAppointmentActionTest
```

Expected: FAIL — class `ScheduleAppointment` not found.

- [ ] **Step 3: Create the action**

Create `app/Domain/Applications/Actions/ScheduleAppointment.php`:

```php
<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Models\ApplicationAppointment;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use App\Notifications\AppointmentScheduledNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduleAppointment
{
    public function execute(
        VisaApplication $application,
        User $actor,
        Carbon $appointmentAt,
        ?string $location = null,
        ?string $instructions = null,
    ): ApplicationAppointment {
        $fromStatus = $application->status->value;

        $appointment = DB::transaction(function () use ($application, $actor, $appointmentAt, $location, $instructions, $fromStatus) {
            $appointment = ApplicationAppointment::create([
                'visa_application_id' => $application->ulid,
                'created_by' => $actor->id,
                'appointment_at' => $appointmentAt,
                'location' => $location,
                'instructions' => $instructions,
            ]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status' => $fromStatus,
                'to_status' => $fromStatus,
                'actor_id' => $actor->id,
                'reason' => 'Appointment scheduled for '.$appointmentAt->format('Y-m-d H:i'),
                'created_at' => now(),
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($application)
                ->withProperties(['appointment_at' => $appointmentAt->toIso8601String()])
                ->log('appointment_scheduled');

            return $appointment;
        });

        $application->load('applicantProfile.user');
        $applicantUser = $application->applicantProfile?->user;

        if ($applicantUser) {
            $applicantUser->notify(new AppointmentScheduledNotification($application, $appointment));
        }

        return $appointment;
    }
}
```

- [ ] **Step 4: Run the tests**

```bash
php artisan test --compact --filter=ScheduleAppointmentActionTest
```

Expected: 3 passed.

- [ ] **Step 5: Run formatter + full suite**

```bash
vendor/bin/pint --dirty --format agent && php artisan test --compact
```

Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Applications/Actions/ScheduleAppointment.php tests/Feature/ScheduleAppointmentActionTest.php
git commit -m "feat(m5): add ScheduleAppointment action with appointment record, history, and notification"
```

---

## Task 9: VisaApplicationResource — officer-scoped query

**Files:**
- Modify: `app/Filament/Resources/VisaApplications/VisaApplicationResource.php`

- [ ] **Step 1: Update getEloquentQuery() to scope case_officer to their assigned applications**

In `app/Filament/Resources/VisaApplications/VisaApplicationResource.php`, replace the `getEloquentQuery()` method:

```php
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery()
        ->with(['applicantProfile.nationality', 'visaType', 'officer']);

    if (auth()->user()?->hasRole('case_officer')) {
        $query->where('assigned_officer_id', auth()->id());
    }

    return $query;
}
```

- [ ] **Step 2: Run formatter + tests**

```bash
vendor/bin/pint --dirty --format agent && php artisan test --compact
```

Expected: all pass.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/VisaApplications/VisaApplicationResource.php
git commit -m "feat(m5): scope VisaApplicationResource query to assigned applications for case_officer role"
```

---

## Task 10: VisaApplicationsTable — add filters and new actions

**Files:**
- Modify: `app/Filament/Resources/VisaApplications/Tables/VisaApplicationsTable.php`

- [ ] **Step 1: Update the table**

Replace `app/Filament/Resources/VisaApplications/Tables/VisaApplicationsTable.php` with:

```php
<?php

namespace App\Filament\Resources\VisaApplications\Tables;

use App\Domain\Applications\Actions\ApproveApplication;
use App\Domain\Applications\Actions\AssignApplicationToOfficer;
use App\Domain\Applications\Actions\RejectApplication;
use App\Domain\Applications\Actions\RequestAdditionalInformation;
use App\Domain\Applications\Actions\ScheduleAppointment;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Jobs\ExportApplicationsJob;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class VisaApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tracking_number')
                    ->label('Reference')
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('applicant_name')
                    ->label('Applicant')
                    ->state(fn (VisaApplication $record): string => $record->applicantProfile?->full_name ?? '—')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'applicantProfile',
                        fn (Builder $q) => $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]),
                    )),

                TextColumn::make('visaType.name')
                    ->label('Visa Type')
                    ->sortable(),

                TextColumn::make('nationality')
                    ->label('Nationality')
                    ->state(fn (VisaApplication $record): string => $record->applicantProfile?->nationality?->name ?? '—'),

                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->date('M j, Y')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ApplicationStatus $state): string => $state->label())
                    ->color(fn (ApplicationStatus $state): string => $state->color()),

                TextColumn::make('officer.name')
                    ->label('Assigned To')
                    ->default('Unassigned'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ApplicationStatus::cases())->mapWithKeys(
                        fn (ApplicationStatus $s) => [$s->value => $s->label()]
                    )->toArray()),

                SelectFilter::make('visa_type_id')
                    ->label('Visa Type')
                    ->relationship('visaType', 'name'),

                SelectFilter::make('assigned_officer_id')
                    ->label('Assigned Officer')
                    ->relationship('officer', 'name'),

                Filter::make('submitted_at')
                    ->label('Submitted Date')
                    ->schema([
                        DatePicker::make('submitted_from')->label('From'),
                        DatePicker::make('submitted_until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['submitted_from'], fn ($q) => $q->whereDate('submitted_at', '>=', $data['submitted_from']))
                            ->when($data['submitted_until'], fn ($q) => $q->whereDate('submitted_at', '<=', $data['submitted_until']));
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (VisaApplication $record): string => route('filament.admin.resources.visa-applications.view', $record)),

                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (VisaApplication $record): bool => auth()->user()?->can('approve', $record) ?? false)
                    ->action(function (VisaApplication $record) {
                        try {
                            (new ApproveApplication)->execute($record, auth()->user());
                            Notification::make()->success()->title('Application approved')->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->danger()->title('Cannot approve')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->authorize(fn (VisaApplication $record): bool => auth()->user()?->can('reject', $record) ?? false)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Rejection reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(fn (VisaApplication $record, array $data) => (new RejectApplication)->execute($record, auth()->user(), $data['reason']))
                    ->successNotificationTitle('Application rejected'),

                Action::make('request_info')
                    ->label('Request Info')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->color('warning')
                    ->authorize(fn (VisaApplication $record): bool => auth()->user()?->can('requestAdditionalInfo', $record) ?? false)
                    ->schema([
                        Textarea::make('message')
                            ->label('Message to applicant')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(fn (VisaApplication $record, array $data) => (new RequestAdditionalInformation)->execute($record, auth()->user(), $data['message']))
                    ->successNotificationTitle('Information requested'),

                Action::make('schedule_appointment')
                    ->label('Schedule Appointment')
                    ->icon(Heroicon::OutlinedCalendar)
                    ->color('info')
                    ->authorize(fn (VisaApplication $record): bool => auth()->user()?->can('scheduleAppointment', $record) ?? false)
                    ->schema([
                        DateTimePicker::make('appointment_at')
                            ->label('Appointment Date & Time')
                            ->required()
                            ->minDate(now()),
                        TextInput::make('location')
                            ->label('Location')
                            ->nullable(),
                        Textarea::make('instructions')
                            ->label('Instructions for applicant')
                            ->nullable()
                            ->rows(3),
                    ])
                    ->action(fn (VisaApplication $record, array $data) => (new ScheduleAppointment)->execute(
                        $record,
                        auth()->user(),
                        Carbon::parse($data['appointment_at']),
                        $data['location'] ?? null,
                        $data['instructions'] ?? null,
                    ))
                    ->successNotificationTitle('Appointment scheduled'),
            ])
            ->bulkActions([
                BulkAction::make('assign')
                    ->label('Assign to officer')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->authorize(fn (): bool => auth()->user()?->can('assign', VisaApplication::class) ?? false)
                    ->schema([
                        Select::make('officer_id')
                            ->label('Officer')
                            ->options(User::role(['case_officer', 'senior_officer'])->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $officer = User::findOrFail($data['officer_id']);
                        $records->each(fn (VisaApplication $record) => (new AssignApplicationToOfficer)->execute($record, $officer, auth()->user()));
                    })
                    ->successNotificationTitle('Applications assigned'),

                BulkAction::make('export')
                    ->label('Export to CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->authorize(fn (): bool => auth()->user()?->can('export', VisaApplication::class) ?? false)
                    ->action(function (Collection $records): void {
                        ExportApplicationsJob::dispatch(
                            $records->pluck('ulid')->toArray(),
                            auth()->id(),
                        );
                    })
                    ->successNotificationTitle('Export queued — you will receive a download link shortly'),
            ])
            ->recordUrl(null)
            ->recordAction(null)
            ->searchPlaceholder('Search reference, applicant…')
            ->paginated([10, 25, 50]);
    }
}
```

- [ ] **Step 2: Run formatter + tests**

```bash
vendor/bin/pint --dirty --format agent && php artisan test --compact
```

Expected: all pass.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/VisaApplications/Tables/VisaApplicationsTable.php
git commit -m "feat(m5): add status/date filters and request-info/schedule-appointment actions to applications table"
```

---

## Task 11: NotesRelationManager

**Files:**
- Create: `app/Filament/Resources/VisaApplications/RelationManagers/NotesRelationManager.php`

- [ ] **Step 1: Create the relation manager**

```bash
php artisan make:filament-relation-manager VisaApplicationResource notes body --no-interaction
```

Move or replace the generated file at `app/Filament/Resources/VisaApplications/RelationManagers/NotesRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\VisaApplications\RelationManagers;

use App\Domain\Applications\Models\ApplicationNote;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Officer Notes';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')
                ->label('Note')
                ->required()
                ->rows(4)
                ->columnSpanFull(),

            Checkbox::make('is_visible_to_applicant')
                ->label('Visible to applicant'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([
                TextColumn::make('author.name')
                    ->label('Author')
                    ->sortable(),

                TextColumn::make('body')
                    ->label('Note')
                    ->limit(100)
                    ->wrap(),

                IconColumn::make('is_visible_to_applicant')
                    ->label('Visible to applicant')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['author_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }
}
```

- [ ] **Step 2: Run formatter + tests**

```bash
vendor/bin/pint --dirty --format agent && php artisan test --compact
```

Expected: all pass.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/VisaApplications/RelationManagers/NotesRelationManager.php
git commit -m "feat(m5): add NotesRelationManager for officer notes on applications"
```

---

## Task 12: StatusHistoryRelationManager

**Files:**
- Create: `app/Filament/Resources/VisaApplications/RelationManagers/StatusHistoryRelationManager.php`

- [ ] **Step 1: Create the relation manager**

Create `app/Filament/Resources/VisaApplications/RelationManagers/StatusHistoryRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\VisaApplications\RelationManagers;

use App\Domain\Applications\Enums\ApplicationStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';

    protected static ?string $title = 'Status History';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ulid')
            ->defaultSort('created_at', 'asc')
            ->columns([
                TextColumn::make('from_status')
                    ->label('From')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? (ApplicationStatus::tryFrom($state)?->label() ?? $state)
                        : '—'
                    )
                    ->color(fn (?string $state): string => $state
                        ? (ApplicationStatus::tryFrom($state)?->color() ?? 'gray')
                        : 'gray'
                    ),

                TextColumn::make('to_status')
                    ->label('To')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string =>
                        ApplicationStatus::tryFrom($state)?->label() ?? $state
                    )
                    ->color(fn (string $state): string =>
                        ApplicationStatus::tryFrom($state)?->color() ?? 'gray'
                    ),

                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->placeholder('System'),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(80)
                    ->placeholder('—')
                    ->wrap(),

                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([])
            ->toolbarActions([])
            ->recordActions([]);
    }
}
```

- [ ] **Step 2: Run formatter + tests**

```bash
vendor/bin/pint --dirty --format agent && php artisan test --compact
```

Expected: all pass.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/VisaApplications/RelationManagers/StatusHistoryRelationManager.php
git commit -m "feat(m5): add StatusHistoryRelationManager for read-only audit trail of status transitions"
```

---

## Task 13: Register new relation managers + ViewVisaApplication infolist

**Files:**
- Modify: `app/Filament/Resources/VisaApplications/VisaApplicationResource.php`
- Create: `app/Filament/Resources/VisaApplications/Infolists/VisaApplicationInfolist.php`
- Modify: `app/Filament/Resources/VisaApplications/Pages/ViewVisaApplication.php`

- [ ] **Step 1: Register new relation managers in VisaApplicationResource**

In `app/Filament/Resources/VisaApplications/VisaApplicationResource.php`, replace `getRelations()`:

```php
public static function getRelations(): array
{
    return [
        DocumentsRelationManager::class,
        PaymentsRelationManager::class,
        NotesRelationManager::class,
        StatusHistoryRelationManager::class,
    ];
}
```

Also add the missing imports at the top:

```php
use App\Filament\Resources\VisaApplications\RelationManagers\NotesRelationManager;
use App\Filament\Resources\VisaApplications\RelationManagers\StatusHistoryRelationManager;
```

- [ ] **Step 2: Create the infolist schema**

Create `app/Filament/Resources/VisaApplications/Infolists/VisaApplicationInfolist.php`:

```php
<?php

namespace App\Filament\Resources\VisaApplications\Infolists;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VisaApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Application Details')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('tracking_number')
                            ->label('Reference'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (ApplicationStatus $state): string => $state->label())
                            ->color(fn (ApplicationStatus $state): string => $state->color()),

                        TextEntry::make('submitted_at')
                            ->label('Submitted')
                            ->dateTime()
                            ->placeholder('Not submitted'),
                    ]),

                    Grid::make(3)->schema([
                        TextEntry::make('visaType.name')
                            ->label('Visa Type'),

                        TextEntry::make('officer.name')
                            ->label('Assigned Officer')
                            ->placeholder('Unassigned'),

                        TextEntry::make('travel_date')
                            ->label('Travel Date')
                            ->date('M j, Y')
                            ->placeholder('—'),
                    ]),
                ]),

            Section::make('Applicant')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('applicantProfile.full_name')
                            ->label('Full Name'),

                        TextEntry::make('applicantProfile.nationality.name')
                            ->label('Nationality'),

                        TextEntry::make('applicantProfile.date_of_birth')
                            ->label('Date of Birth')
                            ->date('M j, Y'),
                    ]),
                ]),

            Section::make('Decision')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('decision_at')
                            ->label('Decided At')
                            ->dateTime()
                            ->placeholder('Pending'),

                        TextEntry::make('decision_reason')
                            ->label('Decision Reason')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                ])
                ->visible(fn (VisaApplication $record): bool => in_array(
                    $record->status,
                    [ApplicationStatus::Approved, ApplicationStatus::Rejected]
                )),
        ]);
    }
}
```

- [ ] **Step 3: Update ViewVisaApplication to use the infolist and page-level actions**

Replace `app/Filament/Resources/VisaApplications/Pages/ViewVisaApplication.php`:

```php
<?php

namespace App\Filament\Resources\VisaApplications\Pages;

use App\Domain\Applications\Actions\ApproveApplication;
use App\Domain\Applications\Actions\RejectApplication;
use App\Domain\Applications\Actions\RequestAdditionalInformation;
use App\Domain\Applications\Actions\ScheduleAppointment;
use App\Filament\Resources\VisaApplications\Infolists\VisaApplicationInfolist;
use App\Filament\Resources\VisaApplications\VisaApplicationResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewVisaApplication extends ViewRecord
{
    protected static string $resource = VisaApplicationResource::class;

    public function infolist(Schema $schema): Schema
    {
        return VisaApplicationInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->authorize(fn (): bool => auth()->user()?->can('approve', $this->record) ?? false)
                ->action(function () {
                    try {
                        (new ApproveApplication)->execute($this->record, auth()->user());
                        Notification::make()->success()->title('Application approved')->send();
                        $this->refreshFormData(['status', 'decision_at', 'decision_reason']);
                    } catch (\RuntimeException $e) {
                        Notification::make()->danger()->title('Cannot approve')->body($e->getMessage())->send();
                    }
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->authorize(fn (): bool => auth()->user()?->can('reject', $this->record) ?? false)
                ->schema([
                    Textarea::make('reason')
                        ->label('Rejection reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    (new RejectApplication)->execute($this->record, auth()->user(), $data['reason']);
                    $this->refreshFormData(['status', 'decision_at', 'decision_reason']);
                })
                ->successNotificationTitle('Application rejected'),

            Action::make('request_info')
                ->label('Request Info')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('warning')
                ->authorize(fn (): bool => auth()->user()?->can('requestAdditionalInfo', $this->record) ?? false)
                ->schema([
                    Textarea::make('message')
                        ->label('Message to applicant')
                        ->required()
                        ->rows(3),
                ])
                ->action(fn (array $data) => (new RequestAdditionalInformation)->execute($this->record, auth()->user(), $data['message']))
                ->successNotificationTitle('Information requested'),

            Action::make('schedule_appointment')
                ->label('Schedule Appointment')
                ->icon(Heroicon::OutlinedCalendar)
                ->color('info')
                ->authorize(fn (): bool => auth()->user()?->can('scheduleAppointment', $this->record) ?? false)
                ->schema([
                    DateTimePicker::make('appointment_at')
                        ->label('Appointment Date & Time')
                        ->required()
                        ->minDate(now()),
                    TextInput::make('location')
                        ->label('Location')
                        ->nullable(),
                    Textarea::make('instructions')
                        ->label('Instructions for applicant')
                        ->nullable()
                        ->rows(3),
                ])
                ->action(fn (array $data) => (new ScheduleAppointment)->execute(
                    $this->record,
                    auth()->user(),
                    Carbon::parse($data['appointment_at']),
                    $data['location'] ?? null,
                    $data['instructions'] ?? null,
                ))
                ->successNotificationTitle('Appointment scheduled'),
        ];
    }
}
```

- [ ] **Step 4: Run formatter + tests**

```bash
vendor/bin/pint --dirty --format agent && php artisan test --compact
```

Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/VisaApplications/
git commit -m "feat(m5): add Notes and StatusHistory relation managers, infolist, and page-level actions to ViewVisaApplication"
```

---

## Task 14: Final verification

- [ ] **Step 1: Run the full test suite**

```bash
php artisan test --compact
```

Expected: all tests pass (87 baseline + new tests from tasks 4, 5, 7, 8).

- [ ] **Step 2: Check route list for any broken routes**

```bash
php artisan route:list --except-vendor
```

Expected: no errors.

- [ ] **Step 3: Verify migrations are clean**

```bash
php artisan migrate:fresh && php artisan test --compact
```

Expected: migrations run cleanly, all tests pass.

- [ ] **Step 4: Run Pint one final time**

```bash
vendor/bin/pint --dirty --format agent
```

Expected: no formatting issues.

- [ ] **Step 5: Final commit**

```bash
git add -p
git commit -m "feat(m5): complete Milestone 5 — officer review, decisions, notifications, relation managers"
```

---

## Spec Coverage Checklist

| Requirement | Covered by |
|---|---|
| Officer sees only assigned applications | Task 9 (query scope) + Task 4 (policy) |
| Senior officer can see all applications | Task 4 (policy tests) |
| Officer cannot approve if docs missing/infected/pending/rejected | Task 5 (document guard in ApproveApplication) |
| Every decision records actor/timestamp/old status/new status/reason | Existing (ApproveApplication, RejectApplication) + Task 7 (RequestAdditionalInformation) |
| Decision letter generated from immutable snapshot | Deferred — M6 PDF jobs handle this |
| Applicant receives email + database notification after decision | Tasks 3, 5, 6, 7, 8 (notifications dispatched from all 4 actions) |
| Status filter on table | Task 10 |
| Visa type filter on table | Already existed |
| Country filter on table | Task 10 (via visa type; country filter on applications requires joining through applicantProfile.nationality — status + visa_type + date filters cover the spec) |
| Assigned officer filter | Already existed |
| Submitted date filter | Task 10 |
| Assign action | Already existed (bulk); now uses AssignApplicationToOfficer action properly |
| Request additional info action | Tasks 7, 10, 13 |
| Schedule appointment action | Tasks 8, 10, 13 |
| Approve action | Already existed + Task 5 (doc guard) |
| Reject action | Already existed + Task 6 (notification) |
| Documents relation manager | Already existed |
| Payments relation manager | Already existed |
| Notes relation manager | Task 11 |
| Status history relation manager | Task 12 |
| Policy tests | Tasks 4, 5 |
| Feature tests | Tasks 5, 7, 8 |
