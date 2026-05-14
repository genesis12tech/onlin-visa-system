# Milestone 3 — Document Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement secure document upload, versioning, officer accept/reject review, and audit logging for the visa application system.

**Architecture:** Document "slots" (`application_documents`) are provisioned when a draft application is created — one slot per required document type on the visa type. Applicants upload files via `UploadDocumentVersion` (validates MIME/size server-side, computes SHA-256, stores privately, creates an immutable version row). Officers accept or reject slots via `AcceptDocument`/`RejectDocument`. Every action is written to `audit_logs`. Files are streamed via a signed, time-limited, auth-guarded download route — never from a public disk.

**Tech Stack:** Laravel 12, PHP 8.4, MySQL 8 (all Milestone 3 migrations already exist), existing `spatie/activitylog`, PHP `hash('sha256')`, Laravel `Storage` with a new `documents` private disk.

---

## File Map

**Create:**
- `app/Domain/Documents/Enums/DocumentStatus.php`
- `app/Domain/Documents/Enums/ScanStatus.php`
- `app/Domain/Documents/Models/ApplicationDocument.php`
- `app/Domain/Documents/Models/DocumentVersion.php`
- `app/Support/AuditLog.php`
- `app/Support/AuditLogger.php`
- `app/Domain/Documents/Policies/ApplicationDocumentPolicy.php`
- `app/Domain/Documents/Actions/UploadDocumentVersion.php`
- `app/Domain/Documents/Actions/AcceptDocument.php`
- `app/Domain/Documents/Actions/RejectDocument.php`
- `app/Http/Controllers/DocumentDownloadController.php`
- `app/Filament/Resources/DocumentTypes/DocumentTypeResource.php`
- `app/Filament/Resources/DocumentTypes/Pages/ListDocumentTypes.php`
- `app/Filament/Resources/DocumentTypes/Pages/CreateDocumentType.php`
- `app/Filament/Resources/DocumentTypes/Pages/EditDocumentType.php`
- `app/Filament/Resources/DocumentTypes/Schemas/DocumentTypeForm.php`
- `app/Filament/Resources/DocumentTypes/Tables/DocumentTypesTable.php`
- `app/Filament/Resources/VisaTypes/RelationManagers/DocumentRequirementsRelationManager.php`
- `app/Filament/Resources/VisaApplications/RelationManagers/DocumentsRelationManager.php`
- `database/factories/DocumentTypeFactory.php`
- `tests/Feature/UploadDocumentVersionTest.php`
- `tests/Feature/AcceptRejectDocumentActionTest.php`
- `tests/Feature/DocumentDownloadTest.php`
- `tests/Unit/ApplicationDocumentPolicyTest.php`

**Modify:**
- `config/filesystems.php` — add `documents` private disk
- `app/Domain/Documents/Models/DocumentType.php` — add `HasFactory` + relations
- `app/Domain/Applications/Models/VisaType.php` — add `documentRequirements()` relation
- `app/Domain/Applications/Models/VisaApplication.php` — add `documents()` relation
- `app/Domain/Applications/Actions/CreateDraftApplication.php` — provision document slots
- `app/Domain/Applications/Actions/SubmitApplication.php` — enforce document upload requirements
- `app/Providers/AppServiceProvider.php` — register `ApplicationDocumentPolicy`
- `app/Filament/Resources/VisaTypes/VisaTypeResource.php` — add `DocumentRequirementsRelationManager`
- `app/Filament/Resources/VisaApplications/VisaApplicationResource.php` — add `DocumentsRelationManager`
- `routes/web.php` — add signed document download route

---

## Task 1: DocumentStatus and ScanStatus Enums

**Files:**
- Create: `app/Domain/Documents/Enums/DocumentStatus.php`
- Create: `app/Domain/Documents/Enums/ScanStatus.php`

- [ ] **Step 1: Create DocumentStatus enum**

Create `app/Domain/Documents/Enums/DocumentStatus.php`:

```php
<?php

namespace App\Domain\Documents\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Uploaded = 'uploaded';
    case PendingScan = 'pending_scan';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Infected = 'infected';
}
```

- [ ] **Step 2: Create ScanStatus enum**

Create `app/Domain/Documents/Enums/ScanStatus.php`:

```php
<?php

namespace App\Domain\Documents\Enums;

enum ScanStatus: string
{
    case Pending = 'pending';
    case Clean = 'clean';
    case Infected = 'infected';
}
```

- [ ] **Step 3: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Documents/Enums/
git commit -m "feat: add DocumentStatus and ScanStatus enums"
```

---

## Task 2: ApplicationDocument and DocumentVersion Models + AuditLog + AuditLogger

**Files:**
- Create: `app/Domain/Documents/Models/ApplicationDocument.php`
- Create: `app/Domain/Documents/Models/DocumentVersion.php`
- Create: `app/Support/AuditLog.php`
- Create: `app/Support/AuditLogger.php`

- [ ] **Step 1: Create ApplicationDocument model**

Create `app/Domain/Documents/Models/ApplicationDocument.php`:

```php
<?php

namespace App\Domain\Documents\Models;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationDocument extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'visa_application_id',
        'document_type_id',
        'current_version_id',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function visaApplication(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'visa_application_id', 'ulid');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id', 'ulid');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id', 'ulid');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'application_document_id', 'ulid');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
```

- [ ] **Step 2: Create DocumentVersion model**

Create `app/Domain/Documents/Models/DocumentVersion.php`:

```php
<?php

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Enums\ScanStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    public $timestamps = false;

    protected $fillable = [
        'application_document_id',
        'storage_path',
        'original_filename',
        'mime_type',
        'file_size_bytes',
        'sha256_checksum',
        'scan_status',
        'scan_completed_at',
        'uploaded_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'scan_status' => ScanStatus::class,
            'scan_completed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function applicationDocument(): BelongsTo
    {
        return $this->belongsTo(ApplicationDocument::class, 'application_document_id', 'ulid');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
```

- [ ] **Step 3: Create AuditLog model**

Create `app/Support/AuditLog.php`:

```php
<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'action',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'subject_type', 'subject_id');
    }
}
```

- [ ] **Step 4: Create AuditLogger service**

Create `app/Support/AuditLogger.php`:

```php
<?php

namespace App\Support;

use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public static function log(
        string $action,
        object $subject,
        array $metadata = [],
        ?int $userId = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }
}
```

- [ ] **Step 5: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Documents/Models/ app/Support/
git commit -m "feat: add ApplicationDocument, DocumentVersion models and AuditLog infrastructure"
```

---

## Task 3: DocumentType Factory + HasFactory

**Files:**
- Create: `database/factories/DocumentTypeFactory.php`
- Modify: `app/Domain/Documents/Models/DocumentType.php`

- [ ] **Step 1: Add HasFactory to DocumentType**

Edit `app/Domain/Documents/Models/DocumentType.php` — add `HasFactory` trait:

```php
<?php

namespace App\Domain\Documents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use HasFactory, HasUlids;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'name',
        'description',
        'accepted_mime_types',
        'max_size_kb',
        'max_pages',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'accepted_mime_types' => 'array',
            'max_size_kb' => 'integer',
            'max_pages' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
```

- [ ] **Step 2: Create DocumentTypeFactory**

Create `database/factories/DocumentTypeFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Domain\Documents\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->words(2, true)),
            'description' => $this->faker->sentence(),
            'accepted_mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
            'max_size_kb' => 5120,
            'max_pages' => null,
            'is_active' => true,
        ];
    }

    public function pdfOnly(): static
    {
        return $this->state([
            'accepted_mime_types' => ['application/pdf'],
            'max_pages' => 10,
        ]);
    }
}
```

- [ ] **Step 3: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Documents/Models/DocumentType.php database/factories/DocumentTypeFactory.php
git commit -m "feat: add HasFactory to DocumentType and DocumentTypeFactory"
```

---

## Task 4: ApplicationDocumentPolicy + AppServiceProvider Registration

**Files:**
- Create: `app/Domain/Documents/Policies/ApplicationDocumentPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Create ApplicationDocumentPolicy**

Create `app/Domain/Documents/Policies/ApplicationDocumentPolicy.php`:

```php
<?php

namespace App\Domain\Documents\Policies;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Models\User;

class ApplicationDocumentPolicy
{
    public function view(User $user, ApplicationDocument $document): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'senior_officer', 'case_officer', 'document_verifier', 'support_staff'])) {
            return true;
        }

        return $document->visaApplication->applicantProfile->user_id === $user->id;
    }

    public function upload(User $user, ApplicationDocument $document): bool
    {
        if ($document->visaApplication->applicantProfile->user_id !== $user->id) {
            return false;
        }

        return in_array($document->visaApplication->status, [
            ApplicationStatus::Draft,
            ApplicationStatus::DocsRequired,
        ]);
    }

    public function accept(User $user, ApplicationDocument $document): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'senior_officer', 'case_officer', 'document_verifier']);
    }

    public function reject(User $user, ApplicationDocument $document): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'senior_officer', 'case_officer', 'document_verifier']);
    }
}
```

- [ ] **Step 2: Register policy in AppServiceProvider**

Edit `app/Providers/AppServiceProvider.php` — add to the `boot()` method:

```php
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Policies\ApplicationDocumentPolicy;

// Inside boot():
Gate::policy(ApplicationDocument::class, ApplicationDocumentPolicy::class);
```

The full updated `boot()`:

```php
public function boot(): void
{
    Gate::policy(User::class, UserPolicy::class);
    Gate::policy(Country::class, CountryPolicy::class);
    Gate::policy(VisaType::class, VisaTypePolicy::class);
    Gate::policy(VisaFee::class, VisaFeePolicy::class);
    Gate::policy(ApplicantProfile::class, ApplicantProfilePolicy::class);
    Gate::policy(VisaApplication::class, VisaApplicationPolicy::class);
    Gate::policy(ApplicationDocument::class, ApplicationDocumentPolicy::class);
}
```

- [ ] **Step 3: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Documents/Policies/ app/Providers/AppServiceProvider.php
git commit -m "feat: add ApplicationDocumentPolicy and register in AppServiceProvider"
```

---

## Task 5: 'documents' Filesystem Disk + Model Relations

**Files:**
- Modify: `config/filesystems.php`
- Modify: `app/Domain/Documents/Models/DocumentType.php`
- Modify: `app/Domain/Applications/Models/VisaType.php`
- Modify: `app/Domain/Applications/Models/VisaApplication.php`

- [ ] **Step 1: Add documents disk to config/filesystems.php**

Add the `documents` disk inside the `'disks'` array in `config/filesystems.php`:

```php
'documents' => [
    'driver' => 'local',
    'root' => storage_path('app/private/documents'),
    'throw' => false,
],
```

- [ ] **Step 2: Add relations to DocumentType**

Edit `app/Domain/Documents/Models/DocumentType.php` — add relations:

```php
use App\Domain\Documents\Models\VisaTypeDocumentRequirement;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Add inside the class:
public function requirements(): HasMany
{
    return $this->hasMany(VisaTypeDocumentRequirement::class, 'document_type_id', 'ulid');
}
```

- [ ] **Step 3: Add documentRequirements() to VisaType**

Edit `app/Domain/Applications/Models/VisaType.php` — add import and method:

```php
use App\Domain\Documents\Models\VisaTypeDocumentRequirement;

// Add inside the class:
public function documentRequirements(): HasMany
{
    return $this->hasMany(VisaTypeDocumentRequirement::class, 'visa_type_id', 'ulid')
        ->orderBy('display_order');
}
```

- [ ] **Step 4: Add documents() to VisaApplication**

Edit `app/Domain/Applications/Models/VisaApplication.php` — add import and method:

```php
use App\Domain\Documents\Models\ApplicationDocument;

// Add inside the class:
public function documents(): HasMany
{
    return $this->hasMany(ApplicationDocument::class, 'visa_application_id', 'ulid');
}
```

- [ ] **Step 5: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```bash
git add config/filesystems.php app/Domain/Documents/Models/DocumentType.php app/Domain/Applications/Models/VisaType.php app/Domain/Applications/Models/VisaApplication.php
git commit -m "feat: add documents filesystem disk and model relations"
```

---

## Task 6: CreateDraftApplication — Provision Document Slots (TDD)

**Files:**
- Modify: `app/Domain/Applications/Actions/CreateDraftApplication.php`
- Test: `tests/Feature/CreateDraftApplicationDocumentSlotsTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CreateDraftApplicationDocumentSlotsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\CreateDraftApplication;
use App\Domain\Applications\Actions\GenerateTrackingNumber;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Documents\Models\VisaTypeDocumentRequirement;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateDraftApplicationDocumentSlotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_document_slots_for_each_required_document_type(): void
    {
        $context = $this->makeContext();

        $docType1 = DocumentType::factory()->create();
        $docType2 = DocumentType::factory()->create();

        VisaTypeDocumentRequirement::create([
            'visa_type_id' => $context['visaType']->ulid,
            'document_type_id' => $docType1->ulid,
            'is_required' => true,
            'display_order' => 1,
        ]);
        VisaTypeDocumentRequirement::create([
            'visa_type_id' => $context['visaType']->ulid,
            'document_type_id' => $docType2->ulid,
            'is_required' => true,
            'display_order' => 2,
        ]);

        $application = (new CreateDraftApplication(new GenerateTrackingNumber))->execute(
            $context['profile'],
            $context['visaType'],
            $context['form'],
        );

        $this->assertDatabaseCount('application_documents', 2);
        $this->assertDatabaseHas('application_documents', [
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType1->ulid,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('application_documents', [
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType2->ulid,
            'status' => 'pending',
        ]);
    }

    public function test_creates_no_document_slots_when_visa_type_has_no_requirements(): void
    {
        $context = $this->makeContext();

        (new CreateDraftApplication(new GenerateTrackingNumber))->execute(
            $context['profile'],
            $context['visaType'],
            $context['form'],
        );

        $this->assertDatabaseCount('application_documents', 0);
    }

    private function makeContext(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $visaType = VisaType::create([
            'name' => 'Tourist',
            'code' => 'TOURIST_30',
            'country_id' => $country->id,
            'processing_days' => 3,
            'validity_days' => 30,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $visaType->ulid,
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

        return compact('country', 'visaType', 'form', 'profile');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter=CreateDraftApplicationDocumentSlotsTest
```

Expected: FAIL — `test_creates_document_slots_for_each_required_document_type` expects 2 rows but finds 0.

- [ ] **Step 3: Modify CreateDraftApplication to provision document slots**

Edit `app/Domain/Applications/Actions/CreateDraftApplication.php`:

```php
<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\VisaTypeDocumentRequirement;
use App\Domain\Identity\Models\ApplicantProfile;
use Illuminate\Support\Facades\DB;

class CreateDraftApplication
{
    public function __construct(private readonly GenerateTrackingNumber $generateTrackingNumber) {}

    public function execute(
        ApplicantProfile $applicantProfile,
        VisaType $visaType,
        FormTemplate $formTemplate,
        ?string $travelDate = null,
    ): VisaApplication {
        return DB::transaction(function () use ($applicantProfile, $visaType, $formTemplate, $travelDate) {
            $application = VisaApplication::create([
                'tracking_number' => $this->generateTrackingNumber->execute(),
                'applicant_profile_id' => $applicantProfile->ulid,
                'visa_type_id' => $visaType->ulid,
                'form_template_id' => $formTemplate->ulid,
                'status' => ApplicationStatus::Draft,
                'travel_date' => $travelDate,
            ]);

            $requirements = VisaTypeDocumentRequirement::where('visa_type_id', $visaType->ulid)
                ->orderBy('display_order')
                ->get();

            foreach ($requirements as $requirement) {
                ApplicationDocument::create([
                    'visa_application_id' => $application->ulid,
                    'document_type_id' => $requirement->document_type_id,
                    'status' => DocumentStatus::Pending,
                ]);
            }

            return $application;
        });
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test --compact --filter=CreateDraftApplicationDocumentSlotsTest
```

Expected: 2 PASSED.

- [ ] **Step 5: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Applications/Actions/CreateDraftApplication.php tests/Feature/CreateDraftApplicationDocumentSlotsTest.php
git commit -m "feat: provision application document slots on draft creation"
```

---

## Task 7: SubmitApplication — Enforce Document Requirements (TDD)

**Files:**
- Modify: `app/Domain/Applications/Actions/SubmitApplication.php`
- Test: `tests/Feature/SubmitApplicationDocumentCheckTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/SubmitApplicationDocumentCheckTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Actions\SubmitApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmitApplicationDocumentCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_fails_if_any_document_slot_is_pending(): void
    {
        $context = $this->makeApplicationWithDocumentSlots(DocumentStatus::Pending);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('All required documents must be uploaded before submission.');

        (new SubmitApplication)->execute($context['application'], $context['actor']);
    }

    public function test_submit_fails_if_any_document_slot_is_rejected(): void
    {
        $context = $this->makeApplicationWithDocumentSlots(DocumentStatus::Rejected);

        $this->expectException(\RuntimeException::class);

        (new SubmitApplication)->execute($context['application'], $context['actor']);
    }

    public function test_submit_succeeds_when_all_document_slots_are_uploaded(): void
    {
        $context = $this->makeApplicationWithDocumentSlots(DocumentStatus::Uploaded);

        $application = (new SubmitApplication)->execute($context['application'], $context['actor']);

        $this->assertEquals(ApplicationStatus::Submitted, $application->status);
    }

    public function test_submit_succeeds_when_there_are_no_document_slots(): void
    {
        $context = $this->makeContext();
        $actor = User::factory()->create();

        $application = VisaApplication::create([
            'tracking_number' => 'VA-TEST-001',
            'applicant_profile_id' => $context['profile']->ulid,
            'visa_type_id' => $context['visaType']->ulid,
            'form_template_id' => $context['form']->ulid,
            'status' => ApplicationStatus::Draft,
        ]);

        $result = (new SubmitApplication)->execute($application, $actor);

        $this->assertEquals(ApplicationStatus::Submitted, $result->status);
    }

    private function makeApplicationWithDocumentSlots(DocumentStatus $docStatus): array
    {
        $context = $this->makeContext();
        $actor = User::factory()->create();
        $docType = DocumentType::factory()->create();

        $application = VisaApplication::create([
            'tracking_number' => 'VA-TEST-001',
            'applicant_profile_id' => $context['profile']->ulid,
            'visa_type_id' => $context['visaType']->ulid,
            'form_template_id' => $context['form']->ulid,
            'status' => ApplicationStatus::Draft,
        ]);

        ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => $docStatus,
        ]);

        return ['application' => $application, 'actor' => $actor];
    }

    private function makeContext(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $visaType = VisaType::create([
            'name' => 'Tourist',
            'code' => 'TOURIST_30',
            'country_id' => $country->id,
            'processing_days' => 3,
            'validity_days' => 30,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $visaType->ulid,
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

        return compact('country', 'visaType', 'form', 'profile');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter=SubmitApplicationDocumentCheckTest
```

Expected: FAIL — the submit action does not yet check documents.

- [ ] **Step 3: Update SubmitApplication to enforce document check**

Edit `app/Domain/Applications/Actions/SubmitApplication.php`:

```php
<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitApplication
{
    public function execute(VisaApplication $application, User $actor): VisaApplication
    {
        return DB::transaction(function () use ($application, $actor) {
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

            return $application->fresh();
        });
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test --compact --filter=SubmitApplicationDocumentCheckTest
```

Expected: 4 PASSED.

- [ ] **Step 5: Verify existing SubmitApplication tests still pass**

```bash
php artisan test --compact --filter=ApproveApplicationActionTest
```

Expected: all PASSED. (The existing tests do not have document slots, so submission proceeds.)

- [ ] **Step 6: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```bash
git add app/Domain/Applications/Actions/SubmitApplication.php tests/Feature/SubmitApplicationDocumentCheckTest.php
git commit -m "feat: enforce document upload requirements before application submission"
```

---

## Task 8: UploadDocumentVersion Action (TDD)

**Files:**
- Create: `app/Domain/Documents/Actions/UploadDocumentVersion.php`
- Create: `tests/Feature/UploadDocumentVersionTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/UploadDocumentVersionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Actions\UploadDocumentVersion;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UploadDocumentVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    public function test_upload_creates_document_version_with_correct_checksum(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        $file = UploadedFile::fake()->createWithContent('passport.pdf', '%PDF-1.4 fake content');
        $expectedChecksum = hash('sha256', '%PDF-1.4 fake content');

        $version = (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);

        $this->assertEquals($expectedChecksum, $version->sha256_checksum);
        $this->assertEquals('passport.pdf', $version->original_filename);
        $this->assertEquals(ScanStatus::Pending, $version->scan_status);
        $this->assertEquals($uploader->id, $version->uploaded_by);
    }

    public function test_upload_stores_file_to_documents_disk(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        $file = UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf');

        $version = (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);

        Storage::disk('documents')->assertExists($version->storage_path);
    }

    public function test_upload_updates_application_document_status_to_uploaded(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        $file = UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf');
        $version = (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);

        $this->assertDatabaseHas('application_documents', [
            'ulid' => $docSlot->ulid,
            'status' => DocumentStatus::Uploaded->value,
            'current_version_id' => $version->ulid,
        ]);
    }

    public function test_upload_sets_current_version_id_on_application_document(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        $file = UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf');
        $version = (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);

        $this->assertEquals($version->ulid, $docSlot->fresh()->current_version_id);
    }

    public function test_upload_writes_audit_log_entry(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        $file = UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf');
        (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.uploaded',
            'subject_type' => ApplicationDocument::class,
            'subject_id' => $docSlot->ulid,
            'user_id' => $uploader->id,
        ]);
    }

    public function test_upload_creates_new_version_for_rejected_document(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();
        $docSlot->update(['status' => DocumentStatus::Rejected, 'rejection_reason' => 'Too blurry']);

        $file = UploadedFile::fake()->create('passport_v2.pdf', 100, 'application/pdf');
        $version = (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);

        $this->assertDatabaseCount('document_versions', 1);
        $this->assertEquals(DocumentStatus::Uploaded, $docSlot->fresh()->status);
        $this->assertEquals($version->ulid, $docSlot->fresh()->current_version_id);
    }

    public function test_upload_fails_if_mime_type_not_accepted(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        // DocumentTypeFactory creates a type that accepts PDF and JPEG — not GIF
        $file = UploadedFile::fake()->create('image.gif', 100, 'image/gif');

        $this->expectException(ValidationException::class);

        (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);
    }

    public function test_upload_fails_if_file_exceeds_max_size(): void
    {
        [$docSlot, $uploader] = $this->makeDocumentSlot();

        // max_size_kb is 5120 (5MB). Create a 6MB file.
        $file = UploadedFile::fake()->create('huge.pdf', 6144, 'application/pdf');

        $this->expectException(ValidationException::class);

        (new UploadDocumentVersion)->execute($docSlot, $file, $uploader);
    }

    private function makeDocumentSlot(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $visaType = VisaType::create([
            'name' => 'Tourist', 'code' => 'TOURIST_30',
            'country_id' => $country->id, 'processing_days' => 3, 'validity_days' => 30,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $visaType->ulid, 'name' => 'Tourist Form', 'schema' => json_encode([]),
        ]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Applicant',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'A12345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Test Street', 'city' => 'London',
        ]);
        $application = VisaApplication::create([
            'tracking_number' => 'VA-TEST-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $visaType->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Draft,
        ]);
        $docType = DocumentType::factory()->create([
            'accepted_mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
            'max_size_kb' => 5120,
        ]);
        $docSlot = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Pending,
        ]);
        $uploader = User::factory()->create();

        return [$docSlot, $uploader];
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter=UploadDocumentVersionTest
```

Expected: FAIL — class `UploadDocumentVersion` does not exist.

- [ ] **Step 3: Create UploadDocumentVersion action**

Create `app/Domain/Documents/Actions/UploadDocumentVersion.php`:

```php
<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\ScanStatus;
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
        $storagePath = 'documents/' . Str::ulid() . '.' . $ext;

        Storage::disk('documents')->put($storagePath, $contents);

        try {
            return DB::transaction(function () use ($document, $file, $uploader, $sha256, $storagePath) {
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
    }

    private function validateFile(UploadedFile $file, DocumentType $documentType): void
    {
        $mimeType = $file->getMimeType();

        if (! in_array($mimeType, $documentType->accepted_mime_types)) {
            throw ValidationException::withMessages([
                'file' => ['File type not accepted. Accepted: ' . implode(', ', $documentType->accepted_mime_types)],
            ]);
        }

        $fileSizeKb = (int) ceil($file->getSize() / 1024);

        if ($fileSizeKb > $documentType->max_size_kb) {
            throw ValidationException::withMessages([
                'file' => ['File exceeds the maximum allowed size of ' . $documentType->max_size_kb . 'KB.'],
            ]);
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test --compact --filter=UploadDocumentVersionTest
```

Expected: 7 PASSED.

- [ ] **Step 5: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Documents/Actions/UploadDocumentVersion.php tests/Feature/UploadDocumentVersionTest.php
git commit -m "feat: implement UploadDocumentVersion action with SHA-256 checksum and private storage"
```

---

## Task 9: AcceptDocument + RejectDocument Actions (TDD)

**Files:**
- Create: `app/Domain/Documents/Actions/AcceptDocument.php`
- Create: `app/Domain/Documents/Actions/RejectDocument.php`
- Create: `tests/Feature/AcceptRejectDocumentActionTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AcceptRejectDocumentActionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Actions\AcceptDocument;
use App\Domain\Documents\Actions\RejectDocument;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcceptRejectDocumentActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_sets_status_to_accepted(): void
    {
        [$docSlot, $officer] = $this->makeUploadedDocument();

        (new AcceptDocument)->execute($docSlot, $officer);

        $this->assertDatabaseHas('application_documents', [
            'ulid' => $docSlot->ulid,
            'status' => DocumentStatus::Accepted->value,
            'reviewed_by' => $officer->id,
        ]);
        $this->assertNotNull($docSlot->fresh()->reviewed_at);
    }

    public function test_accept_writes_audit_log(): void
    {
        [$docSlot, $officer] = $this->makeUploadedDocument();

        (new AcceptDocument)->execute($docSlot, $officer);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.accepted',
            'subject_type' => ApplicationDocument::class,
            'subject_id' => $docSlot->ulid,
            'user_id' => $officer->id,
        ]);
    }

    public function test_reject_sets_status_to_rejected_with_reason(): void
    {
        [$docSlot, $officer] = $this->makeUploadedDocument();

        (new RejectDocument)->execute($docSlot, $officer, 'Passport photo is too dark.');

        $this->assertDatabaseHas('application_documents', [
            'ulid' => $docSlot->ulid,
            'status' => DocumentStatus::Rejected->value,
            'reviewed_by' => $officer->id,
            'rejection_reason' => 'Passport photo is too dark.',
        ]);
    }

    public function test_reject_writes_audit_log_with_reason(): void
    {
        [$docSlot, $officer] = $this->makeUploadedDocument();

        (new RejectDocument)->execute($docSlot, $officer, 'Too blurry');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.rejected',
            'subject_type' => ApplicationDocument::class,
            'subject_id' => $docSlot->ulid,
            'user_id' => $officer->id,
        ]);
    }

    private function makeUploadedDocument(): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $visaType = VisaType::create([
            'name' => 'Tourist', 'code' => 'TOURIST_30',
            'country_id' => $country->id, 'processing_days' => 3, 'validity_days' => 30,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $visaType->ulid, 'name' => 'Tourist Form', 'schema' => json_encode([]),
        ]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Applicant',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'A12345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Test Street', 'city' => 'London',
        ]);
        $application = VisaApplication::create([
            'tracking_number' => 'VA-TEST-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $visaType->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Submitted,
        ]);
        $docType = DocumentType::factory()->create();
        $docSlot = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Uploaded,
        ]);
        $officer = User::factory()->create();

        return [$docSlot, $officer];
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter=AcceptRejectDocumentActionTest
```

Expected: FAIL — `AcceptDocument` and `RejectDocument` do not exist.

- [ ] **Step 3: Create AcceptDocument action**

Create `app/Domain/Documents/Actions/AcceptDocument.php`:

```php
<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class AcceptDocument
{
    public function execute(ApplicationDocument $document, User $officer): ApplicationDocument
    {
        return DB::transaction(function () use ($document, $officer) {
            $document->update([
                'status' => DocumentStatus::Accepted,
                'reviewed_by' => $officer->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            AuditLogger::log('document.accepted', $document, [], $officer->id);

            return $document->fresh();
        });
    }
}
```

- [ ] **Step 4: Create RejectDocument action**

Create `app/Domain/Documents/Actions/RejectDocument.php`:

```php
<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class RejectDocument
{
    public function execute(ApplicationDocument $document, User $officer, string $reason): ApplicationDocument
    {
        return DB::transaction(function () use ($document, $officer, $reason) {
            $document->update([
                'status' => DocumentStatus::Rejected,
                'reviewed_by' => $officer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            AuditLogger::log('document.rejected', $document, ['reason' => $reason], $officer->id);

            return $document->fresh();
        });
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test --compact --filter=AcceptRejectDocumentActionTest
```

Expected: 4 PASSED.

- [ ] **Step 6: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```bash
git add app/Domain/Documents/Actions/ tests/Feature/AcceptRejectDocumentActionTest.php
git commit -m "feat: implement AcceptDocument and RejectDocument actions with audit logging"
```

---

## Task 10: Document Download Controller + Signed Route (TDD)

**Files:**
- Create: `app/Http/Controllers/DocumentDownloadController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/DocumentDownloadTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/DocumentDownloadTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Documents\Models\DocumentVersion;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'case_officer', 'guard_name' => 'web']);
    }

    public function test_download_returns_403_when_scan_status_is_pending(): void
    {
        [$version, $officer] = $this->makeVersionWithOfficer(ScanStatus::Pending);

        $url = URL::temporarySignedRoute('documents.download', now()->addMinutes(15), [
            'version' => $version->ulid,
        ]);

        $this->actingAs($officer)->get($url)->assertForbidden();
    }

    public function test_download_returns_403_when_scan_status_is_infected(): void
    {
        [$version, $officer] = $this->makeVersionWithOfficer(ScanStatus::Infected);

        $url = URL::temporarySignedRoute('documents.download', now()->addMinutes(15), [
            'version' => $version->ulid,
        ]);

        $this->actingAs($officer)->get($url)->assertForbidden();
    }

    public function test_download_succeeds_when_scan_status_is_clean(): void
    {
        [$version, $officer] = $this->makeVersionWithOfficer(ScanStatus::Clean);

        Storage::disk('documents')->put($version->storage_path, 'fake pdf content');

        $url = URL::temporarySignedRoute('documents.download', now()->addMinutes(15), [
            'version' => $version->ulid,
        ]);

        $this->actingAs($officer)->get($url)->assertSuccessful();
    }

    public function test_download_writes_audit_log_on_success(): void
    {
        [$version, $officer] = $this->makeVersionWithOfficer(ScanStatus::Clean);

        Storage::disk('documents')->put($version->storage_path, 'fake pdf content');

        $url = URL::temporarySignedRoute('documents.download', now()->addMinutes(15), [
            'version' => $version->ulid,
        ]);

        $this->actingAs($officer)->get($url);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.downloaded',
            'user_id' => $officer->id,
        ]);
    }

    public function test_download_returns_403_for_unauthenticated_user(): void
    {
        [$version] = $this->makeVersionWithOfficer(ScanStatus::Clean);

        $url = URL::temporarySignedRoute('documents.download', now()->addMinutes(15), [
            'version' => $version->ulid,
        ]);

        $this->get($url)->assertRedirect('/login');
    }

    public function test_download_returns_403_for_unauthorized_user(): void
    {
        [$version] = $this->makeVersionWithOfficer(ScanStatus::Clean);

        Storage::disk('documents')->put($version->storage_path, 'fake pdf content');

        $otherUser = User::factory()->create(); // no role, different applicant

        $url = URL::temporarySignedRoute('documents.download', now()->addMinutes(15), [
            'version' => $version->ulid,
        ]);

        $this->actingAs($otherUser)->get($url)->assertForbidden();
    }

    private function makeVersionWithOfficer(ScanStatus $scanStatus): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $visaType = VisaType::create([
            'name' => 'Tourist', 'code' => 'TOURIST_30',
            'country_id' => $country->id, 'processing_days' => 3, 'validity_days' => 30,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $visaType->ulid, 'name' => 'Tourist Form', 'schema' => json_encode([]),
        ]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Applicant',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'A12345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Test Street', 'city' => 'London',
        ]);
        $application = VisaApplication::create([
            'tracking_number' => 'VA-TEST-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $visaType->ulid,
            'form_template_id' => $form->ulid,
            'status' => ApplicationStatus::Submitted,
        ]);
        $docType = DocumentType::factory()->create();
        $docSlot = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Uploaded,
        ]);
        $version = DocumentVersion::create([
            'application_document_id' => $docSlot->ulid,
            'storage_path' => 'documents/test-file.pdf',
            'original_filename' => 'passport.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1024,
            'sha256_checksum' => hash('sha256', 'fake content'),
            'scan_status' => $scanStatus,
            'uploaded_by' => $user->id,
            'created_at' => now(),
        ]);

        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        return [$version, $officer];
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter=DocumentDownloadTest
```

Expected: FAIL — route `documents.download` does not exist.

- [ ] **Step 3: Register the route in routes/web.php**

Open `routes/web.php` and add:

```php
use App\Http\Controllers\DocumentDownloadController;

Route::get('/documents/{version}/download', [DocumentDownloadController::class, 'download'])
    ->name('documents.download')
    ->middleware(['auth', 'signed']);
```

- [ ] **Step 4: Create DocumentDownloadController**

Create `app/Http/Controllers/DocumentDownloadController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Models\DocumentVersion;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    public function download(Request $request, DocumentVersion $version): StreamedResponse
    {
        $document = $version->applicationDocument;

        $this->authorize('view', $document);

        abort_if(
            $version->scan_status !== ScanStatus::Clean,
            403,
            'Document is not cleared for download.',
        );

        AuditLogger::log('document.downloaded', $version, [
            'storage_path' => $version->storage_path,
        ], auth()->id());

        return Storage::disk('documents')->download(
            $version->storage_path,
            $version->original_filename,
        );
    }
}
```

Note: The `DocumentVersion` route model binding uses `ulid` as the route key. Add `getRouteKeyName()` to `DocumentVersion`:

Edit `app/Domain/Documents/Models/DocumentVersion.php` — add:

```php
public function getRouteKeyName(): string
{
    return 'ulid';
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test --compact --filter=DocumentDownloadTest
```

Expected: 6 PASSED. (The unauthenticated test expects a redirect to `/login`.)

- [ ] **Step 6: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/DocumentDownloadController.php app/Domain/Documents/Models/DocumentVersion.php routes/web.php tests/Feature/DocumentDownloadTest.php
git commit -m "feat: add signed document download route with scan status and policy enforcement"
```

---

## Task 11: ApplicationDocumentPolicy Tests

**Files:**
- Create: `tests/Unit/ApplicationDocumentPolicyTest.php`

- [ ] **Step 1: Write the policy tests**

Create `tests/Unit/ApplicationDocumentPolicyTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Documents\Policies\ApplicationDocumentPolicy;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplicationDocumentPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected ApplicationDocumentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->policy = new ApplicationDocumentPolicy;

        foreach (['super_admin', 'admin', 'case_officer', 'senior_officer', 'document_verifier', 'support_staff', 'applicant'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_applicant_can_view_their_own_document(): void
    {
        [$docSlot, $applicantUser] = $this->makeDocumentForUser();

        $this->assertTrue($this->policy->view($applicantUser, $docSlot));
    }

    public function test_applicant_cannot_view_another_applicants_document(): void
    {
        [$docSlot] = $this->makeDocumentForUser();
        $otherUser = User::factory()->create();

        $this->assertFalse($this->policy->view($otherUser, $docSlot));
    }

    public function test_case_officer_can_view_any_document(): void
    {
        [$docSlot] = $this->makeDocumentForUser();
        $officer = User::factory()->create();
        $officer->assignRole('case_officer');

        $this->assertTrue($this->policy->view($officer, $docSlot));
    }

    public function test_applicant_can_upload_to_draft_application(): void
    {
        [$docSlot, $applicantUser] = $this->makeDocumentForUser(ApplicationStatus::Draft);

        $this->assertTrue($this->policy->upload($applicantUser, $docSlot));
    }

    public function test_applicant_cannot_upload_to_submitted_application(): void
    {
        [$docSlot, $applicantUser] = $this->makeDocumentForUser(ApplicationStatus::Submitted);

        $this->assertFalse($this->policy->upload($applicantUser, $docSlot));
    }

    public function test_applicant_can_upload_when_docs_required(): void
    {
        [$docSlot, $applicantUser] = $this->makeDocumentForUser(ApplicationStatus::DocsRequired);

        $this->assertTrue($this->policy->upload($applicantUser, $docSlot));
    }

    public function test_other_applicant_cannot_upload_to_document(): void
    {
        [$docSlot] = $this->makeDocumentForUser(ApplicationStatus::Draft);
        $otherUser = User::factory()->create();

        $this->assertFalse($this->policy->upload($otherUser, $docSlot));
    }

    public function test_document_verifier_can_accept_document(): void
    {
        [$docSlot] = $this->makeDocumentForUser();
        $verifier = User::factory()->create();
        $verifier->assignRole('document_verifier');

        $this->assertTrue($this->policy->accept($verifier, $docSlot));
    }

    public function test_document_verifier_can_reject_document(): void
    {
        [$docSlot] = $this->makeDocumentForUser();
        $verifier = User::factory()->create();
        $verifier->assignRole('document_verifier');

        $this->assertTrue($this->policy->reject($verifier, $docSlot));
    }

    public function test_applicant_cannot_accept_document(): void
    {
        [$docSlot, $applicantUser] = $this->makeDocumentForUser();

        $this->assertFalse($this->policy->accept($applicantUser, $docSlot));
    }

    private function makeDocumentForUser(ApplicationStatus $status = ApplicationStatus::Submitted): array
    {
        $country = Country::create(['name' => 'Test', 'iso2' => 'TE', 'iso3' => 'TST']);
        $visaType = VisaType::create([
            'name' => 'Tourist', 'code' => 'TOURIST_30',
            'country_id' => $country->id, 'processing_days' => 3, 'validity_days' => 30,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $visaType->ulid, 'name' => 'Tourist Form', 'schema' => json_encode([]),
        ]);
        $applicantUser = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $applicantUser->id, 'first_name' => 'Test', 'last_name' => 'Applicant',
            'date_of_birth' => '1990-01-01', 'gender' => 'male',
            'nationality_id' => $country->id, 'country_of_residence_id' => $country->id,
            'passport_number' => 'A12345678', 'passport_expiry_date' => '2030-01-01',
            'phone' => '+1234567890', 'address_line_1' => '1 Test Street', 'city' => 'London',
        ]);
        $application = VisaApplication::create([
            'tracking_number' => 'VA-TEST-001',
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id' => $visaType->ulid,
            'form_template_id' => $form->ulid,
            'status' => $status,
        ]);
        $docType = DocumentType::factory()->create();
        $docSlot = ApplicationDocument::create([
            'visa_application_id' => $application->ulid,
            'document_type_id' => $docType->ulid,
            'status' => DocumentStatus::Uploaded,
        ]);

        return [$docSlot, $applicantUser];
    }
}
```

- [ ] **Step 2: Run tests**

```bash
php artisan test --compact --filter=ApplicationDocumentPolicyTest
```

Expected: 11 PASSED.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/ApplicationDocumentPolicyTest.php
git commit -m "test: add ApplicationDocumentPolicy tests"
```

---

## Task 12: DocumentTypeResource (Filament Admin Panel)

**Files:**
- Create: `app/Filament/Resources/DocumentTypes/DocumentTypeResource.php`
- Create: `app/Filament/Resources/DocumentTypes/Pages/ListDocumentTypes.php`
- Create: `app/Filament/Resources/DocumentTypes/Pages/CreateDocumentType.php`
- Create: `app/Filament/Resources/DocumentTypes/Pages/EditDocumentType.php`
- Create: `app/Filament/Resources/DocumentTypes/Schemas/DocumentTypeForm.php`
- Create: `app/Filament/Resources/DocumentTypes/Tables/DocumentTypesTable.php`

Follow the same pattern as the existing `VisaTypes` resource.

- [ ] **Step 1: Create DocumentTypeForm**

Create `app/Filament/Resources/DocumentTypes/Schemas/DocumentTypeForm.php`:

```php
<?php

namespace App\Filament\Resources\DocumentTypes\Schemas;

use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DocumentTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(2)
                    ->nullable(),
                TagsInput::make('accepted_mime_types')
                    ->label('Accepted MIME Types')
                    ->placeholder('application/pdf')
                    ->helperText('e.g. application/pdf, image/jpeg, image/png')
                    ->required(),
                TextInput::make('max_size_kb')
                    ->label('Max File Size (KB)')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->default(5120)
                    ->helperText('5120 = 5MB'),
                TextInput::make('max_pages')
                    ->label('Max Pages')
                    ->numeric()
                    ->nullable()
                    ->minValue(1)
                    ->helperText('Leave blank for no page limit'),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
```

- [ ] **Step 2: Create DocumentTypesTable**

Create `app/Filament/Resources/DocumentTypes/Tables/DocumentTypesTable.php`:

```php
<?php

namespace App\Filament\Resources\DocumentTypes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('accepted_mime_types')
                    ->label('Accepted Types')
                    ->badge()
                    ->separator(','),
                TextColumn::make('max_size_kb')
                    ->label('Max Size')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1024, 1) . 'MB'),
                TextColumn::make('max_pages')
                    ->label('Max Pages')
                    ->placeholder('Unlimited'),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

- [ ] **Step 3: Create page classes**

Create `app/Filament/Resources/DocumentTypes/Pages/ListDocumentTypes.php`:

```php
<?php

namespace App\Filament\Resources\DocumentTypes\Pages;

use App\Filament\Resources\DocumentTypes\DocumentTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocumentTypes extends ListRecords
{
    protected static string $resource = DocumentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

Create `app/Filament/Resources/DocumentTypes/Pages/CreateDocumentType.php`:

```php
<?php

namespace App\Filament\Resources\DocumentTypes\Pages;

use App\Filament\Resources\DocumentTypes\DocumentTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDocumentType extends CreateRecord
{
    protected static string $resource = DocumentTypeResource::class;
}
```

Create `app/Filament/Resources/DocumentTypes/Pages/EditDocumentType.php`:

```php
<?php

namespace App\Filament\Resources\DocumentTypes\Pages;

use App\Filament\Resources\DocumentTypes\DocumentTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDocumentType extends EditRecord
{
    protected static string $resource = DocumentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
```

- [ ] **Step 4: Create DocumentTypeResource**

Create `app/Filament/Resources/DocumentTypes/DocumentTypeResource.php`:

```php
<?php

namespace App\Filament\Resources\DocumentTypes;

use App\Domain\Documents\Models\DocumentType;
use App\Filament\Resources\DocumentTypes\Pages\CreateDocumentType;
use App\Filament\Resources\DocumentTypes\Pages\EditDocumentType;
use App\Filament\Resources\DocumentTypes\Pages\ListDocumentTypes;
use App\Filament\Resources\DocumentTypes\Schemas\DocumentTypeForm;
use App\Filament\Resources\DocumentTypes\Tables\DocumentTypesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DocumentTypeResource extends Resource
{
    protected static ?string $model = DocumentType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'SETTINGS';

    protected static ?string $navigationLabel = 'Document Types';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DocumentTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentTypes::route('/'),
            'create' => CreateDocumentType::route('/create'),
            'edit' => EditDocumentType::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 5: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Verify admin panel loads**

```bash
php artisan route:list --path=admin --except-vendor
```

Confirm that document-types routes appear.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/DocumentTypes/
git commit -m "feat: add DocumentTypeResource to Filament admin panel"
```

---

## Task 13: DocumentRequirementsRelationManager on VisaTypeResource

**Files:**
- Create: `app/Filament/Resources/VisaTypes/RelationManagers/DocumentRequirementsRelationManager.php`
- Modify: `app/Filament/Resources/VisaTypes/VisaTypeResource.php`

- [ ] **Step 1: Create DocumentRequirementsRelationManager**

Create `app/Filament/Resources/VisaTypes/RelationManagers/DocumentRequirementsRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\VisaTypes\RelationManagers;

use App\Domain\Documents\Models\DocumentType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentRequirementsRelationManager extends RelationManager
{
    protected static string $relationship = 'documentRequirements';

    protected static ?string $title = 'Required Documents';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('document_type_id')
                    ->label('Document Type')
                    ->options(DocumentType::where('is_active', true)->pluck('name', 'ulid'))
                    ->required()
                    ->searchable(),
                TextInput::make('display_order')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_required')
                    ->label('Required')
                    ->default(true),
                Textarea::make('notes')
                    ->label('Instructions for Applicant')
                    ->rows(2)
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('document_type_id')
            ->columns([
                TextColumn::make('documentType.name')
                    ->label('Document Type')
                    ->sortable(),
                TextColumn::make('display_order')
                    ->label('Order')
                    ->sortable(),
                IconColumn::make('is_required')
                    ->label('Required')
                    ->boolean(),
                TextColumn::make('notes')
                    ->limit(50)
                    ->placeholder('—'),
            ])
            ->defaultSort('display_order')
            ->filters([])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

- [ ] **Step 2: Register the relation manager in VisaTypeResource**

Edit `app/Filament/Resources/VisaTypes/VisaTypeResource.php` — update `getRelations()`:

```php
use App\Filament\Resources\VisaTypes\RelationManagers\DocumentRequirementsRelationManager;
use App\Filament\Resources\VisaTypes\RelationManagers\FeesRelationManager;

public static function getRelations(): array
{
    return [
        FeesRelationManager::class,
        DocumentRequirementsRelationManager::class,
    ];
}
```

- [ ] **Step 3: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/VisaTypes/RelationManagers/DocumentRequirementsRelationManager.php app/Filament/Resources/VisaTypes/VisaTypeResource.php
git commit -m "feat: add DocumentRequirementsRelationManager to VisaTypeResource"
```

---

## Task 14: DocumentsRelationManager on VisaApplicationResource

**Files:**
- Create: `app/Filament/Resources/VisaApplications/RelationManagers/DocumentsRelationManager.php`
- Modify: `app/Filament/Resources/VisaApplications/VisaApplicationResource.php`

- [ ] **Step 1: Create DocumentsRelationManager**

Create `app/Filament/Resources/VisaApplications/RelationManagers/DocumentsRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\VisaApplications\RelationManagers;

use App\Domain\Documents\Actions\AcceptDocument;
use App\Domain\Documents\Actions\RejectDocument;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Models\ApplicationDocument;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ulid')
            ->columns([
                TextColumn::make('documentType.name')
                    ->label('Document Type')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (DocumentStatus $state): string => match ($state) {
                        DocumentStatus::Accepted => 'success',
                        DocumentStatus::Rejected, DocumentStatus::Infected => 'danger',
                        DocumentStatus::Uploaded, DocumentStatus::UnderReview => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('currentVersion.original_filename')
                    ->label('Filename')
                    ->placeholder('Not uploaded'),
                TextColumn::make('currentVersion.scan_status')
                    ->label('Scan')
                    ->badge()
                    ->color(fn (?ScanStatus $state): string => match ($state) {
                        ScanStatus::Clean => 'success',
                        ScanStatus::Infected => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('—'),
                TextColumn::make('reviewer.name')
                    ->label('Reviewed By')
                    ->placeholder('—'),
                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->filters([])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (ApplicationDocument $record): string =>
                        $record->currentVersion
                            ? URL::temporarySignedRoute(
                                'documents.download',
                                now()->addMinutes(15),
                                ['version' => $record->currentVersion->ulid],
                            )
                            : '#'
                    )
                    ->openUrlInNewTab()
                    ->visible(fn (ApplicationDocument $record): bool =>
                        $record->currentVersion !== null
                        && $record->currentVersion->scan_status === ScanStatus::Clean
                    )
                    ->authorize(fn (ApplicationDocument $record): bool =>
                        auth()->user()?->can('view', $record) ?? false
                    ),

                Action::make('accept')
                    ->label('Accept')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (ApplicationDocument $record) =>
                        (new AcceptDocument)->execute($record, auth()->user())
                    )
                    ->visible(fn (ApplicationDocument $record): bool =>
                        in_array($record->status, [DocumentStatus::Uploaded, DocumentStatus::UnderReview])
                    )
                    ->authorize(fn (ApplicationDocument $record): bool =>
                        auth()->user()?->can('accept', $record) ?? false
                    ),

                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(fn (ApplicationDocument $record, array $data) =>
                        (new RejectDocument)->execute($record, auth()->user(), $data['rejection_reason'])
                    )
                    ->visible(fn (ApplicationDocument $record): bool =>
                        in_array($record->status, [DocumentStatus::Uploaded, DocumentStatus::UnderReview, DocumentStatus::Accepted])
                    )
                    ->authorize(fn (ApplicationDocument $record): bool =>
                        auth()->user()?->can('reject', $record) ?? false
                    ),

                Action::make('mark_scan_clean')
                    ->label('Mark Scan Clean')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (ApplicationDocument $record) => $record->currentVersion?->update([
                        'scan_status' => ScanStatus::Clean,
                        'scan_completed_at' => now(),
                    ]))
                    ->visible(fn (ApplicationDocument $record): bool =>
                        $record->currentVersion !== null
                        && $record->currentVersion->scan_status === ScanStatus::Pending
                    )
                    ->authorize(fn (ApplicationDocument $record): bool =>
                        auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false
                    ),
            ])
            ->headerActions([])
            ->toolbarActions([]);
    }
}
```

- [ ] **Step 2: Register the relation manager in VisaApplicationResource**

Edit `app/Filament/Resources/VisaApplications/VisaApplicationResource.php` — update `getRelations()`:

```php
use App\Filament\Resources\VisaApplications\RelationManagers\DocumentsRelationManager;

public static function getRelations(): array
{
    return [
        DocumentsRelationManager::class,
    ];
}
```

- [ ] **Step 3: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Run full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass. Note the count — it should include all tests from previous milestones plus the new tests added in this milestone.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/VisaApplications/RelationManagers/DocumentsRelationManager.php app/Filament/Resources/VisaApplications/VisaApplicationResource.php
git commit -m "feat: add DocumentsRelationManager to VisaApplicationResource with accept/reject actions"
```

---

## Self-Review Checklist

**Spec coverage:**

| Acceptance Criterion | Task |
|---|---|
| Required document rules enforced before submission | Task 7 |
| Required document rules enforced before officer review | Task 14 (visible in UI; scan + policy gates) |
| Applicant can replace a rejected document | Task 8 (UploadDocumentVersion handles re-upload) |
| Every replacement creates a new `document_versions` row | Task 8 |
| `application_documents.current_version_id` points to latest version | Task 8 |
| Documents never accessible via public URL | Task 10 (private disk + auth + signed route) |
| Unauthorized users cannot view or download documents | Tasks 10–11 (policy + scan gate) |
| Every document action writes an audit log entry | Tasks 8, 9 (upload, accept, reject), Task 10 (download) |
| `ApplicationDocumentPolicy` required | Tasks 4, 11 |
| Signed, time-limited URLs for previews | Task 10 (temporarySignedRoute) |
| Scan status must be `clean` before download | Tasks 10–11 |
| Document slot provisioning on draft creation | Task 6 |
| DocumentTypeResource in admin panel | Task 12 |
| DocumentRequirementsRelationManager on VisaType | Task 13 |
| DocumentsRelationManager on VisaApplication | Task 14 |

**Security rules verified:**
- Rule 4 (private storage only): `documents` disk uses `storage_path('app/private/documents')` — never public
- Rule 5 (signed time-limited URLs): `URL::temporarySignedRoute()` with 15-minute expiry
- Rule 6 (ULID storage paths): `UploadDocumentVersion` generates `documents/{ulid}.{ext}`
- Rule 7 (block download until scan clean): `DocumentDownloadController` enforces `ScanStatus::Clean`
- Rule 13 (server-side validation): `UploadDocumentVersion::validateFile()` checks MIME and size
- Rule 18 (audit logs): all five actions write to `audit_logs`
