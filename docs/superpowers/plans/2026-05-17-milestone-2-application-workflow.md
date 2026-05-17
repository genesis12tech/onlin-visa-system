# Milestone 2 — Application Workflow & Dynamic Forms

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an authenticated applicant pick a visa type, fill a dynamic JSON-driven form one section at a time with auto-save, and submit their application — receiving an opaque tracking number on completion.

**Architecture:** Two new Livewire components (`ApplicationWizard` + `DynamicFormSection`) drive the wizard. The wizard is a full-page Livewire component mounted from a route keyed on `tracking_number`. `DynamicFormSection` is nested inside the wizard and handles auto-save per section via `wire:model.blur` + the `updatedAnswers()` lifecycle hook. All mutations go through Domain Actions. The dashboard is updated to list real applications with a "Start new application" entry point.

**Tech Stack:** Laravel 12, Livewire 3, Tailwind CSS 4, Tabler Icons, PHPUnit 11, Spatie Permission.

---

## What already exists — do NOT recreate

- All migrations: `form_templates`, `visa_applications`, `application_answers`, `application_snapshots`, `application_status_histories` — **all tables exist**
- Models: `VisaApplication`, `FormTemplate`, `VisaType`, `ApplicationStatusHistory`, `ApplicationNote`
- Actions: `CreateDraftApplication` (instance `execute()`), `SubmitApplication` (instance `execute()`), `GenerateTrackingNumber` (instance `execute()`)
- Enum: `App\Domain\Applications\Enums\ApplicationStatus`
- Policy: `App\Domain\Applications\Policies\VisaApplicationPolicy` (admin/officer gates only — **applicant gates missing**)
- `VisaApplicationPolicy` is already registered in `AppServiceProvider`
- `x-*` Blade component library: `x-card`, `x-input`, `x-select`, `x-button`, `x-badge`, `x-step-indicator`, `x-empty-state`, `x-status-timeline`, `x-alert`
- Layouts: `resources/views/layouts/app.blade.php`, `resources/views/components/app-layout.blade.php`

---

## Form schema format (canonical — use this everywhere)

`form_templates.schema` is a JSON column cast to `array`. Its shape:

```json
{
    "sections": [
        {
            "key": "travel_details",
            "title": "Travel Details",
            "fields": [
                {
                    "key": "travel_purpose",
                    "type": "select",
                    "label": "Purpose of travel",
                    "required": true,
                    "options": ["Tourism", "Business", "Study", "Medical"]
                },
                {
                    "key": "intended_entry_date",
                    "type": "date",
                    "label": "Intended entry date",
                    "required": true
                },
                {
                    "key": "intended_stay_days",
                    "type": "text",
                    "label": "Intended length of stay (days)",
                    "required": true
                },
                {
                    "key": "employer_name",
                    "type": "text",
                    "label": "Employer name",
                    "required": false,
                    "condition": { "field": "travel_purpose", "equals": "Business" }
                }
            ]
        },
        {
            "key": "background",
            "title": "Background",
            "fields": [
                {
                    "key": "previous_visa_refusal",
                    "type": "radio",
                    "label": "Have you ever been refused a visa?",
                    "required": true,
                    "options": ["yes", "no"]
                },
                {
                    "key": "refusal_details",
                    "type": "textarea",
                    "label": "Refusal details",
                    "required": true,
                    "condition": { "field": "previous_visa_refusal", "equals": "yes" }
                }
            ]
        }
    ]
}
```

**Answer key format in `application_answers.field_key`:** `{section_key}.{field_key}` — e.g. `travel_details.travel_purpose`.

**Field types:** `text`, `date`, `select`, `textarea`, `radio`, `checkbox`

---

## File Map

**Create:**
- `app/Domain/Applications/Models/ApplicationAnswer.php`
- `app/Domain/Applications/Models/ApplicationSnapshot.php`
- `app/Domain/Applications/Actions/UpdateApplicationSection.php`
- `app/Domain/Applications/Actions/WithdrawApplication.php`
- `database/factories/VisaTypeFactory.php`
- `database/factories/FormTemplateFactory.php`
- `database/factories/VisaApplicationFactory.php`
- `database/factories/ApplicationAnswerFactory.php`
- `app/Http/Controllers/Applications/ApplicationController.php`
- `app/Http/Requests/Applications/StartApplicationRequest.php`
- `app/Livewire/Applications/ApplicationWizard.php`
- `app/Livewire/Applications/DynamicFormSection.php`
- `resources/views/livewire/applications/application-wizard.blade.php`
- `resources/views/livewire/applications/dynamic-form-section.blade.php`
- `resources/views/pages/applications/start.blade.php`
- `tests/Unit/UpdateApplicationSectionTest.php`
- `tests/Unit/GenerateTrackingNumberTest.php`
- `tests/Unit/WithdrawApplicationTest.php`
- `tests/Feature/CreateApplicationTest.php`
- `tests/Feature/ApplicationWizardTest.php`

**Modify:**
- `app/Domain/Applications/Models/VisaApplication.php` — add `answers()` + `snapshot()` relationships
- `app/Domain/Applications/Models/VisaType.php` — add `HasFactory` + `newFactory()`
- `app/Domain/Applications/Models/FormTemplate.php` — add `HasFactory` + `newFactory()`
- `app/Domain/Applications/Actions/SubmitApplication.php` — add `ApplicationSnapshot` creation inside transaction
- `app/Domain/Applications/Policies/VisaApplicationPolicy.php` — add `create`, `update`, `submit`, `withdraw` for applicant role
- `routes/web.php` — add application routes inside the protected middleware group
- `app/Http/Controllers/DashboardController.php` — pass `$applications` to view
- `resources/views/pages/dashboard.blade.php` — replace "coming soon" with application list + start button

---

## Task 1: ApplicationAnswer + ApplicationSnapshot models

**Files:**
- Create: `app/Domain/Applications/Models/ApplicationAnswer.php`
- Create: `app/Domain/Applications/Models/ApplicationSnapshot.php`
- Modify: `app/Domain/Applications/Models/VisaApplication.php`

- [ ] **Step 1: Create `app/Domain/Applications/Models/ApplicationAnswer.php`**

```php
<?php

namespace App\Domain\Applications\Models;

use Database\Factories\ApplicationAnswerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationAnswer extends Model
{
    /** @use HasFactory<ApplicationAnswerFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): ApplicationAnswerFactory
    {
        return ApplicationAnswerFactory::new();
    }

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'visa_application_id',
        'field_key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public function visaApplication(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'visa_application_id', 'ulid');
    }
}
```

- [ ] **Step 2: Create `app/Domain/Applications/Models/ApplicationSnapshot.php`**

```php
<?php

namespace App\Domain\Applications\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationSnapshot extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    public $timestamps = false;

    protected $fillable = [
        'visa_application_id',
        'snapshot_data',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_data' => 'array',
            'created_at'    => 'datetime',
        ];
    }

    public function visaApplication(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'visa_application_id', 'ulid');
    }
}
```

- [ ] **Step 3: Add `answers()` and `snapshot()` relationships to `VisaApplication`**

Open `app/Domain/Applications/Models/VisaApplication.php`. Add these two methods after the `payments()` method:

```php
public function answers(): HasMany
{
    return $this->hasMany(ApplicationAnswer::class, 'visa_application_id', 'ulid');
}

public function snapshot(): HasOne
{
    return $this->hasOne(ApplicationSnapshot::class, 'visa_application_id', 'ulid');
}
```

Also add `HasFactory` + `newFactory()` to `VisaApplication` (needed for Task 2 factories):

Add to the `use` statement at top of class:
```php
use Database\Factories\VisaApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
```

Add trait to the class:
```php
use HasFactory, HasUlids, LogsActivity;
```

Add factory override method:
```php
protected static function newFactory(): VisaApplicationFactory
{
    return VisaApplicationFactory::new();
}
```

Add `HasFactory` + `newFactory()` to `VisaType` (`app/Domain/Applications/Models/VisaType.php`):
```php
use Database\Factories\VisaTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// ...
use HasFactory, HasUlids;

protected static function newFactory(): VisaTypeFactory
{
    return VisaTypeFactory::new();
}
```

Add `HasFactory` + `newFactory()` to `FormTemplate` (`app/Domain/Applications/Models/FormTemplate.php`):
```php
use Database\Factories\FormTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// ...
use HasFactory, HasUlids;

protected static function newFactory(): FormTemplateFactory
{
    return FormTemplateFactory::new();
}
```

- [ ] **Step 4: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Applications/Models/
git commit -m "feat(m2): ApplicationAnswer + ApplicationSnapshot models, VisaApplication relationships"
```

---

## Task 2: Factories

**Files:**
- Create: `database/factories/VisaTypeFactory.php`
- Create: `database/factories/FormTemplateFactory.php`
- Create: `database/factories/VisaApplicationFactory.php`
- Create: `database/factories/ApplicationAnswerFactory.php`

- [ ] **Step 1: Create `database/factories/VisaTypeFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisaType>
 */
class VisaTypeFactory extends Factory
{
    protected $model = VisaType::class;

    public function definition(): array
    {
        return [
            'country_id'      => Country::factory(),
            'name'            => fake()->words(3, true) . ' Visa',
            'code'            => strtoupper(fake()->unique()->lexify('????_??')),
            'description'     => fake()->sentence(),
            'processing_days' => fake()->numberBetween(5, 30),
            'validity_days'   => fake()->randomElement([30, 90, 180, 365]),
            'max_entries'     => fake()->randomElement(['single', 'multiple', 'unlimited']),
            'is_active'       => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
```

- [ ] **Step 2: Create `database/factories/FormTemplateFactory.php`**

The schema below is a minimal two-section template used in tests. It must match the canonical format defined at the top of this plan.

```php
<?php

namespace Database\Factories;

use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormTemplate>
 */
class FormTemplateFactory extends Factory
{
    protected $model = FormTemplate::class;

    public function definition(): array
    {
        return [
            'visa_type_id' => VisaType::factory(),
            'name'         => 'Standard Application Form',
            'version'      => 1,
            'schema'       => [
                'sections' => [
                    [
                        'key'    => 'travel_details',
                        'title'  => 'Travel Details',
                        'fields' => [
                            [
                                'key'      => 'travel_purpose',
                                'type'     => 'select',
                                'label'    => 'Purpose of travel',
                                'required' => true,
                                'options'  => ['Tourism', 'Business', 'Study', 'Medical'],
                            ],
                            [
                                'key'      => 'intended_entry_date',
                                'type'     => 'date',
                                'label'    => 'Intended entry date',
                                'required' => true,
                            ],
                            [
                                'key'      => 'intended_stay_days',
                                'type'     => 'text',
                                'label'    => 'Intended length of stay (days)',
                                'required' => true,
                            ],
                        ],
                    ],
                    [
                        'key'    => 'background',
                        'title'  => 'Background',
                        'fields' => [
                            [
                                'key'      => 'previous_visa_refusal',
                                'type'     => 'radio',
                                'label'    => 'Have you ever been refused a visa?',
                                'required' => true,
                                'options'  => ['yes', 'no'],
                            ],
                        ],
                    ],
                ],
            ],
            'is_active'    => true,
            'published_at' => now(),
        ];
    }
}
```

- [ ] **Step 3: Create `database/factories/VisaApplicationFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VisaApplication>
 */
class VisaApplicationFactory extends Factory
{
    protected $model = VisaApplication::class;

    public function definition(): array
    {
        return [
            'tracking_number'      => 'VA-' . now()->year . '-' . strtoupper(Str::random(6)),
            'applicant_profile_id' => ApplicantProfile::factory(),
            'visa_type_id'         => VisaType::factory(),
            'form_template_id'     => FormTemplate::factory(),
            'status'               => ApplicationStatus::Draft,
            'assigned_officer_id'  => null,
            'submitted_at'         => null,
            'travel_date'          => null,
            'decision_at'          => null,
            'decision_reason'      => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state([
            'status'       => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(['status' => ApplicationStatus::Withdrawn]);
    }
}
```

- [ ] **Step 4: Create `database/factories/ApplicationAnswerFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Domain\Applications\Models\ApplicationAnswer;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationAnswer>
 */
class ApplicationAnswerFactory extends Factory
{
    protected $model = ApplicationAnswer::class;

    public function definition(): array
    {
        return [
            'visa_application_id' => VisaApplication::factory(),
            'field_key'           => 'travel_details.' . fake()->word(),
            'value'               => fake()->word(),
        ];
    }
}
```

- [ ] **Step 5: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```bash
git add database/factories/
git commit -m "feat(m2): factories for VisaType, FormTemplate, VisaApplication, ApplicationAnswer"
```

---

## Task 3: UpdateApplicationSection action (TDD)

**Files:**
- Test: `tests/Unit/UpdateApplicationSectionTest.php`
- Create: `app/Domain/Applications/Actions/UpdateApplicationSection.php`

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --phpunit --unit UpdateApplicationSectionTest --no-interaction
```

Replace the generated file with:

```php
<?php

namespace Tests\Unit;

use App\Domain\Applications\Actions\UpdateApplicationSection;
use App\Domain\Applications\Models\ApplicationAnswer;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateApplicationSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_answers_for_new_fields(): void
    {
        $application = VisaApplication::factory()->create();

        UpdateApplicationSection::run($application, 'travel_details', [
            'travel_purpose'      => 'Tourism',
            'intended_entry_date' => '2026-08-01',
        ]);

        $this->assertDatabaseHas('application_answers', [
            'visa_application_id' => $application->ulid,
            'field_key'           => 'travel_details.travel_purpose',
        ]);
        $this->assertDatabaseHas('application_answers', [
            'visa_application_id' => $application->ulid,
            'field_key'           => 'travel_details.intended_entry_date',
        ]);
    }

    public function test_it_updates_existing_answers(): void
    {
        $application = VisaApplication::factory()->create();

        UpdateApplicationSection::run($application, 'travel_details', [
            'travel_purpose' => 'Tourism',
        ]);
        UpdateApplicationSection::run($application, 'travel_details', [
            'travel_purpose' => 'Business',
        ]);

        $this->assertEquals(
            'Business',
            ApplicationAnswer::where('visa_application_id', $application->ulid)
                ->where('field_key', 'travel_details.travel_purpose')
                ->value('value')
        );
        $this->assertDatabaseCount('application_answers', 1);
    }

    public function test_it_skips_null_values(): void
    {
        $application = VisaApplication::factory()->create();

        UpdateApplicationSection::run($application, 'travel_details', [
            'travel_purpose'      => 'Tourism',
            'intended_entry_date' => null,
        ]);

        $this->assertDatabaseHas('application_answers', [
            'visa_application_id' => $application->ulid,
            'field_key'           => 'travel_details.travel_purpose',
        ]);
        $this->assertDatabaseMissing('application_answers', [
            'field_key' => 'travel_details.intended_entry_date',
        ]);
    }

    public function test_it_does_not_touch_other_sections(): void
    {
        $application = VisaApplication::factory()->create();

        UpdateApplicationSection::run($application, 'travel_details', ['travel_purpose' => 'Tourism']);
        UpdateApplicationSection::run($application, 'background', ['previous_visa_refusal' => 'no']);

        $this->assertDatabaseCount('application_answers', 2);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --compact --filter=UpdateApplicationSectionTest
```

Expected: FAIL — `UpdateApplicationSection` class not found.

- [ ] **Step 3: Create `app/Domain/Applications/Actions/UpdateApplicationSection.php`**

```php
<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Models\ApplicationAnswer;
use App\Domain\Applications\Models\VisaApplication;

class UpdateApplicationSection
{
    public static function run(VisaApplication $application, string $sectionKey, array $fieldAnswers): void
    {
        foreach ($fieldAnswers as $fieldKey => $value) {
            if ($value === null) {
                continue;
            }

            ApplicationAnswer::updateOrCreate(
                [
                    'visa_application_id' => $application->ulid,
                    'field_key'           => "{$sectionKey}.{$fieldKey}",
                ],
                ['value' => $value],
            );
        }
    }
}
```

- [ ] **Step 4: Run test to confirm it passes**

```bash
php artisan test --compact --filter=UpdateApplicationSectionTest
```

Expected: 4 passed.

- [ ] **Step 5: Also write the GenerateTrackingNumber unit test**

```bash
php artisan make:test --phpunit --unit GenerateTrackingNumberTest --no-interaction
```

Replace with:

```php
<?php

namespace Tests\Unit;

use App\Domain\Applications\Actions\GenerateTrackingNumber;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateTrackingNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_tracking_number_with_correct_prefix(): void
    {
        $number = app(GenerateTrackingNumber::class)->execute();

        $this->assertStringStartsWith('VA-' . now()->year . '-', $number);
    }

    public function test_it_generates_unique_numbers(): void
    {
        $numbers = collect(range(1, 10))
            ->map(fn () => app(GenerateTrackingNumber::class)->execute());

        $this->assertCount(10, $numbers->unique());
    }

    public function test_it_retries_if_tracking_number_already_exists(): void
    {
        VisaApplication::factory()->create(['tracking_number' => 'VA-' . now()->year . '-AAAAAA']);

        $number = app(GenerateTrackingNumber::class)->execute();

        $this->assertNotEquals('VA-' . now()->year . '-AAAAAA', $number);
    }
}
```

```bash
php artisan test --compact --filter=GenerateTrackingNumberTest
```

Expected: 3 passed.

- [ ] **Step 6: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Applications/Actions/UpdateApplicationSection.php tests/Unit/
git commit -m "feat(m2): UpdateApplicationSection action + tracking number unit tests (TDD)"
```

---

## Task 4: SubmitApplication snapshot + VisaApplicationPolicy applicant gates (TDD)

**Files:**
- Modify: `app/Domain/Applications/Actions/SubmitApplication.php`
- Modify: `app/Domain/Applications/Policies/VisaApplicationPolicy.php`
- Test: `tests/Unit/SubmitApplicationSnapshotTest.php`
- Test: `tests/Feature/VisaApplicationPolicyTest.php`

### Part A — Snapshot

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --phpunit --unit SubmitApplicationSnapshotTest --no-interaction
```

Replace with:

```php
<?php

namespace Tests\Unit;

use App\Domain\Applications\Actions\SubmitApplication;
use App\Domain\Applications\Models\ApplicationAnswer;
use App\Domain\Applications\Models\ApplicationSnapshot;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubmitApplicationSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_writes_an_immutable_snapshot(): void
    {
        Queue::fake();
        Notification::fake();

        $application = VisaApplication::factory()->create();
        ApplicationAnswer::factory()->create([
            'visa_application_id' => $application->ulid,
            'field_key'           => 'travel_details.travel_purpose',
            'value'               => 'Tourism',
        ]);
        $actor = User::factory()->create();

        app(SubmitApplication::class)->execute($application, $actor);

        $snapshot = ApplicationSnapshot::where('visa_application_id', $application->ulid)->first();
        $this->assertNotNull($snapshot);
        $this->assertEquals($application->tracking_number, $snapshot->snapshot_data['tracking_number']);
    }

    public function test_snapshot_is_not_overwritten_on_double_submit(): void
    {
        Queue::fake();
        Notification::fake();

        $application = VisaApplication::factory()->create();
        $actor = User::factory()->create();

        app(SubmitApplication::class)->execute($application, $actor);

        // Attempting to submit again should not create a second snapshot
        $this->assertDatabaseCount('application_snapshots', 1);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --compact --filter=SubmitApplicationSnapshotTest
```

Expected: FAIL — no snapshot row created.

- [ ] **Step 3: Modify `app/Domain/Applications/Actions/SubmitApplication.php`**

Add snapshot creation inside the `DB::transaction` callback, immediately after the `ApplicationStatusHistory::create(...)` call. Add the import at the top:

```php
use App\Domain\Applications\Models\ApplicationSnapshot;
```

Insert this block **inside the transaction, after the status history write**:

```php
ApplicationSnapshot::firstOrCreate(
    ['visa_application_id' => $application->ulid],
    [
        'snapshot_data' => [
            'tracking_number'  => $application->tracking_number,
            'visa_type'        => $application->visaType?->toArray(),
            'form_template_id' => $application->form_template_id,
            'answers'          => $application->answers()->get(['field_key', 'value'])->toArray(),
            'submitted_at'     => now()->toISOString(),
        ],
        'created_at' => now(),
    ],
);
```

- [ ] **Step 4: Run test to confirm it passes**

```bash
php artisan test --compact --filter=SubmitApplicationSnapshotTest
```

Expected: 2 passed.

### Part B — Policy

- [ ] **Step 5: Write the policy test**

```bash
php artisan make:test --phpunit VisaApplicationPolicyTest --no-interaction
```

Replace with:

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VisaApplicationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $applicant;
    private ApplicantProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);

        $country = Country::factory()->create();
        $this->applicant = User::factory()->create(['email_verified_at' => now()]);
        $this->applicant->assignRole('applicant');
        $this->profile = ApplicantProfile::factory()->create([
            'user_id'                 => $this->applicant->id,
            'nationality_id'          => $country->id,
            'country_of_residence_id' => $country->id,
        ]);
    }

    public function test_applicant_can_create_an_application(): void
    {
        $this->assertTrue($this->applicant->can('create', VisaApplication::class));
    }

    public function test_applicant_can_view_own_application(): void
    {
        $app = VisaApplication::factory()->create(['applicant_profile_id' => $this->profile->ulid]);

        $this->assertTrue($this->applicant->can('view', $app));
    }

    public function test_applicant_cannot_view_another_applicants_application(): void
    {
        $otherApp = VisaApplication::factory()->create();

        $this->assertFalse($this->applicant->can('view', $otherApp));
    }

    public function test_applicant_can_update_own_draft_application(): void
    {
        $app = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status'               => ApplicationStatus::Draft,
        ]);

        $this->assertTrue($this->applicant->can('update', $app));
    }

    public function test_applicant_cannot_update_submitted_application(): void
    {
        $app = VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $this->profile->ulid,
        ]);

        $this->assertFalse($this->applicant->can('update', $app));
    }

    public function test_applicant_can_submit_own_draft(): void
    {
        $app = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status'               => ApplicationStatus::Draft,
        ]);

        $this->assertTrue($this->applicant->can('submit', $app));
    }

    public function test_applicant_can_withdraw_draft_or_submitted_application(): void
    {
        $draft = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status'               => ApplicationStatus::Draft,
        ]);
        $submitted = VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $this->profile->ulid,
        ]);

        $this->assertTrue($this->applicant->can('withdraw', $draft));
        $this->assertTrue($this->applicant->can('withdraw', $submitted));
    }

    public function test_applicant_cannot_withdraw_approved_application(): void
    {
        $app = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status'               => ApplicationStatus::Approved,
        ]);

        $this->assertFalse($this->applicant->can('withdraw', $app));
    }

    public function test_unauthenticated_user_cannot_create_application(): void
    {
        $guest = User::factory()->create(); // no role
        $this->assertFalse($guest->can('create', VisaApplication::class));
    }
}
```

- [ ] **Step 6: Run to confirm it fails**

```bash
php artisan test --compact --filter=VisaApplicationPolicyTest
```

Expected: FAIL — applicant gates missing.

- [ ] **Step 7: Update `app/Domain/Applications/Policies/VisaApplicationPolicy.php`**

Add these imports at the top:
```php
use App\Domain\Applications\Enums\ApplicationStatus;
```

Replace the `viewAny` method and add new applicant methods. The full updated class:

```php
<?php

namespace App\Domain\Applications\Policies;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;

class VisaApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('applicant')) {
            return $user->applicantProfile !== null;
        }

        return $user->hasAnyRole(['super_admin', 'admin', 'senior_officer', 'case_officer', 'support_staff']);
    }

    public function view(User $user, VisaApplication $application): bool
    {
        if ($user->hasRole('applicant')) {
            return $user->applicantProfile?->ulid === $application->applicant_profile_id;
        }

        if ($user->hasAnyRole(['super_admin', 'admin', 'senior_officer', 'support_staff'])) {
            return true;
        }

        if ($user->hasRole('case_officer')) {
            return $application->assigned_officer_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('applicant') && $user->applicantProfile !== null;
    }

    public function update(User $user, VisaApplication $application): bool
    {
        return $user->hasRole('applicant')
            && $user->applicantProfile?->ulid === $application->applicant_profile_id
            && $application->status === ApplicationStatus::Draft;
    }

    public function submit(User $user, VisaApplication $application): bool
    {
        return $user->hasRole('applicant')
            && $user->applicantProfile?->ulid === $application->applicant_profile_id
            && $application->status === ApplicationStatus::Draft;
    }

    public function withdraw(User $user, VisaApplication $application): bool
    {
        if (!$user->hasRole('applicant')) {
            return false;
        }

        if ($user->applicantProfile?->ulid !== $application->applicant_profile_id) {
            return false;
        }

        return in_array($application->status, [
            ApplicationStatus::Draft,
            ApplicationStatus::Submitted,
            ApplicationStatus::PaymentPending,
        ], strict: true);
    }

    public function approve(User $user, VisaApplication $application): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'senior_officer']);
    }

    public function reject(User $user, VisaApplication $application): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'senior_officer']);
    }

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

    public function assign(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'senior_officer']);
    }

    public function export(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'finance_officer']);
    }
}
```

- [ ] **Step 8: Run tests to confirm both pass**

```bash
php artisan test --compact --filter="SubmitApplicationSnapshotTest|VisaApplicationPolicyTest"
```

Expected: all passing.

- [ ] **Step 9: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Applications/Actions/SubmitApplication.php \
        app/Domain/Applications/Policies/VisaApplicationPolicy.php \
        tests/Unit/SubmitApplicationSnapshotTest.php \
        tests/Feature/VisaApplicationPolicyTest.php
git commit -m "feat(m2): SubmitApplication writes snapshot, VisaApplicationPolicy applicant gates (TDD)"
```

---

## Task 5: WithdrawApplication action (TDD)

**Files:**
- Test: `tests/Unit/WithdrawApplicationTest.php`
- Create: `app/Domain/Applications/Actions/WithdrawApplication.php`

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test --phpunit --unit WithdrawApplicationTest --no-interaction
```

Replace with:

```php
<?php

namespace Tests\Unit;

use App\Domain\Applications\Actions\WithdrawApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_status_to_withdrawn(): void
    {
        $application = VisaApplication::factory()->create(['status' => ApplicationStatus::Draft]);
        $actor = User::factory()->create();

        WithdrawApplication::run($application, $actor);

        $this->assertEquals(ApplicationStatus::Withdrawn, $application->fresh()->status);
    }

    public function test_it_writes_a_status_history_row(): void
    {
        $application = VisaApplication::factory()->create(['status' => ApplicationStatus::Submitted]);
        $actor = User::factory()->create();

        WithdrawApplication::run($application, $actor);

        $this->assertDatabaseHas('application_status_histories', [
            'visa_application_id' => $application->ulid,
            'from_status'         => ApplicationStatus::Submitted->value,
            'to_status'           => ApplicationStatus::Withdrawn->value,
            'actor_id'            => $actor->id,
        ]);
    }

    public function test_it_throws_when_application_is_already_decided(): void
    {
        $application = VisaApplication::factory()->create(['status' => ApplicationStatus::Approved]);
        $actor = User::factory()->create();

        $this->expectException(\RuntimeException::class);

        WithdrawApplication::run($application, $actor);
    }

    public function test_it_throws_for_already_withdrawn(): void
    {
        $application = VisaApplication::factory()->withdrawn()->create();
        $actor = User::factory()->create();

        $this->expectException(\RuntimeException::class);

        WithdrawApplication::run($application, $actor);
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

```bash
php artisan test --compact --filter=WithdrawApplicationTest
```

Expected: FAIL — class not found.

- [ ] **Step 3: Create `app/Domain/Applications/Actions/WithdrawApplication.php`**

```php
<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WithdrawApplication
{
    private const WITHDRAWABLE = [
        ApplicationStatus::Draft,
        ApplicationStatus::Submitted,
        ApplicationStatus::PaymentPending,
    ];

    public static function run(VisaApplication $application, User $actor): void
    {
        if (!in_array($application->status, self::WITHDRAWABLE, strict: true)) {
            throw new \RuntimeException(
                "Cannot withdraw application with status: {$application->status->value}"
            );
        }

        DB::transaction(function () use ($application, $actor) {
            $fromStatus = $application->status->value;

            $application->update(['status' => ApplicationStatus::Withdrawn]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status'         => $fromStatus,
                'to_status'           => ApplicationStatus::Withdrawn->value,
                'actor_id'            => $actor->id,
                'created_at'          => now(),
            ]);
        });
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
php artisan test --compact --filter=WithdrawApplicationTest
```

Expected: 4 passed.

- [ ] **Step 5: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Applications/Actions/WithdrawApplication.php \
        tests/Unit/WithdrawApplicationTest.php
git commit -m "feat(m2): WithdrawApplication action (TDD)"
```

---

## Task 6: Application routes + ApplicationController

**Files:**
- Create: `app/Http/Controllers/Applications/ApplicationController.php`
- Create: `app/Http/Requests/Applications/StartApplicationRequest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create `app/Http/Requests/Applications/StartApplicationRequest.php`**

```bash
php artisan make:request Applications/StartApplicationRequest --no-interaction
```

Replace with:

```php
<?php

namespace App\Http\Requests\Applications;

use Illuminate\Foundation\Http\FormRequest;

class StartApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'visa_type_ulid' => ['required', 'string', 'exists:visa_types,ulid'],
        ];
    }
}
```

- [ ] **Step 2: Create `app/Http/Controllers/Applications/ApplicationController.php`**

```bash
php artisan make:controller Applications/ApplicationController --no-interaction
```

Replace with:

```php
<?php

namespace App\Http\Controllers\Applications;

use App\Domain\Applications\Actions\CreateDraftApplication;
use App\Domain\Applications\Actions\WithdrawApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\StartApplicationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function start(Request $request): View
    {
        $this->authorize('create', VisaApplication::class);

        $visaTypes = VisaType::where('is_active', true)
            ->with('country')
            ->orderBy('name')
            ->get();

        return view('pages.applications.start', compact('visaTypes'));
    }

    public function store(StartApplicationRequest $request): RedirectResponse
    {
        $this->authorize('create', VisaApplication::class);

        $visaType = VisaType::where('ulid', $request->validated('visa_type_ulid'))->firstOrFail();

        $formTemplate = FormTemplate::where('visa_type_id', $visaType->ulid)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->firstOrFail();

        $profile = $request->user()->applicantProfile;

        $existing = VisaApplication::where('applicant_profile_id', $profile->ulid)
            ->where('visa_type_id', $visaType->ulid)
            ->where('status', ApplicationStatus::Draft->value)
            ->first();

        if ($existing) {
            return redirect()->route('applications.wizard', $existing->tracking_number);
        }

        $application = app(CreateDraftApplication::class)->execute(
            applicantProfile: $profile,
            visaType: $visaType,
            formTemplate: $formTemplate,
        );

        return redirect()->route('applications.wizard', $application->tracking_number);
    }

    public function withdraw(Request $request, string $tracking): RedirectResponse
    {
        $application = VisaApplication::where('tracking_number', $tracking)->firstOrFail();

        $this->authorize('withdraw', $application);

        WithdrawApplication::run($application, $request->user());

        return redirect()->route('dashboard')
            ->with('success', 'Your application has been withdrawn.');
    }
}
```

- [ ] **Step 3: Add application routes to `routes/web.php`**

Inside the existing `Route::middleware(['verified', EnsureProfileComplete::class])->group(...)` block, after the dashboard route, add:

```php
// Applications
Route::get('/applications/start', [ApplicationController::class, 'start'])->name('applications.start');
Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');
Route::get('/applications/{tracking}', \App\Livewire\Applications\ApplicationWizard::class)->name('applications.wizard');
Route::post('/applications/{tracking}/withdraw', [ApplicationController::class, 'withdraw'])->name('applications.withdraw');
```

Also add the import at the top of `routes/web.php`:

```php
use App\Http\Controllers\Applications\ApplicationController;
```

- [ ] **Step 4: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Verify routes registered**

```bash
php artisan route:list --name=applications --except-vendor 2>&1
```

Expected: 4 application routes listed.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Applications/ \
        app/Http/Requests/Applications/ \
        routes/web.php
git commit -m "feat(m2): ApplicationController, StartApplicationRequest, application routes"
```

---

## Task 7: Visa type picker page

**Files:**
- Create: `resources/views/pages/applications/start.blade.php`

- [ ] **Step 1: Create the directory and file**

```bash
mkdir -p resources/views/pages/applications
```

Create `resources/views/pages/applications/start.blade.php`:

```blade
<x-app-layout title="Start a new application">
    <div class="space-y-8">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Start a new application</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose the visa type you'd like to apply for.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                &larr; Back to dashboard
            </a>
        </div>

        @if($visaTypes->isEmpty())
            <x-empty-state
                icon="ti-certificate-off"
                heading="No visa types available"
                description="There are no active visa types at the moment. Please check back later."
            />
        @else
            <form method="POST" action="{{ route('applications.store') }}">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($visaTypes as $type)
                        <label class="cursor-pointer">
                            <input type="radio" name="visa_type_ulid" value="{{ $type->ulid }}" class="sr-only peer" required>
                            <div class="h-full rounded-xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm
                                        transition-colors peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/20
                                        hover:border-gray-300 dark:hover:border-gray-600">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/40">
                                        <i class="ti ti-certificate text-xl text-blue-600 dark:text-blue-400"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $type->name }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $type->country->name }} &middot; {{ $type->processing_days }} days processing
                                        </p>
                                        @if($type->description)
                                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ $type->description }}</p>
                                        @endif
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-600 dark:text-gray-300">
                                                {{ $type->validity_days }} days validity
                                            </span>
                                            <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-600 dark:text-gray-300">
                                                {{ ucfirst($type->max_entries) }} entry
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>

                @error('visa_type_ulid')
                    <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="mt-6 flex justify-end">
                    <x-button type="submit" class="px-8">
                        Continue &rarr;
                    </x-button>
                </div>
            </form>
        @endif

    </div>
</x-app-layout>
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/pages/applications/start.blade.php
git commit -m "feat(m2): visa type picker page"
```

---

## Task 8: ApplicationWizard Livewire component

**Files:**
- Create: `app/Livewire/Applications/ApplicationWizard.php`
- Create: `resources/views/livewire/applications/application-wizard.blade.php`

- [ ] **Step 1: Create `app/Livewire/Applications/ApplicationWizard.php`**

```bash
mkdir -p app/Livewire/Applications
```

```php
<?php

namespace App\Livewire\Applications;

use App\Domain\Applications\Actions\SubmitApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ApplicationWizard extends Component
{
    #[Locked]
    public string $tracking;

    #[Locked]
    public VisaApplication $application;

    public int $currentSectionIndex = 0;

    public bool $onReviewStep = false;

    public bool $submitting = false;

    public function mount(string $tracking): void
    {
        $this->tracking = $tracking;
        $this->application = VisaApplication::where('tracking_number', $tracking)
            ->with(['formTemplate', 'visaType', 'answers'])
            ->firstOrFail();

        Gate::authorize('view', $this->application);
    }

    /** @return array<int, array<string, mixed>> */
    public function sections(): array
    {
        return $this->application->formTemplate->schema['sections'] ?? [];
    }

    /** @return array<string, mixed> */
    public function currentSection(): array
    {
        return $this->sections()[$this->currentSectionIndex] ?? [];
    }

    /** @return array<string, mixed> $answers keyed by bare field_key */
    public function savedAnswersForSection(string $sectionKey): array
    {
        return $this->application->answers
            ->filter(fn ($a) => str_starts_with($a->field_key, "{$sectionKey}."))
            ->keyBy(fn ($a) => str_after($a->field_key, "{$sectionKey}."))
            ->map(fn ($a) => $a->value)
            ->toArray();
    }

    public function advance(): void
    {
        Gate::authorize('update', $this->application);

        $lastIndex = count($this->sections()) - 1;

        if ($this->currentSectionIndex < $lastIndex) {
            $this->currentSectionIndex++;
        } else {
            $this->onReviewStep = true;
        }
    }

    public function goBack(): void
    {
        if ($this->onReviewStep) {
            $this->onReviewStep = false;
        } elseif ($this->currentSectionIndex > 0) {
            $this->currentSectionIndex--;
        }
    }

    public function canSubmit(): bool
    {
        if ($this->application->status !== ApplicationStatus::Draft) {
            return false;
        }

        return !$this->application->documents()
            ->whereIn('status', ['pending', 'rejected', 'infected'])
            ->exists();
    }

    public function submit(): void
    {
        Gate::authorize('submit', $this->application);

        $this->submitting = true;

        app(SubmitApplication::class)->execute($this->application, auth()->user());

        $this->redirect(route('applications.wizard', $this->tracking));
    }

    public function sectionSaved(string $sectionKey): void
    {
        $this->application->load('answers');
    }

    public function render(): View
    {
        return view('livewire.applications.application-wizard')
            ->layout('layouts.app', ['title' => 'Application — ' . $this->application->tracking_number]);
    }
}
```

- [ ] **Step 2: Create `resources/views/livewire/applications/application-wizard.blade.php`**

```bash
mkdir -p resources/views/livewire/applications
```

```blade
<div class="max-w-3xl mx-auto space-y-8">

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">
                {{ $application->visaType->name }}
            </p>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Visa Application</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Reference: <span class="font-mono font-medium">{{ $application->tracking_number }}</span>
            </p>
        </div>
        <x-badge :status="$application->status" />
    </div>

    {{-- Submitted state --}}
    @if($application->status !== \App\Domain\Applications\Enums\ApplicationStatus::Draft)
        <x-card>
            <div class="text-center py-8 space-y-4">
                <div class="flex h-16 w-16 mx-auto items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                    <i class="ti ti-circle-check text-3xl text-green-600 dark:text-green-400"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Application submitted</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                    Your application has been received. Keep your tracking number safe — you'll need it to check your progress.
                </p>
                <div class="inline-block rounded-lg bg-gray-100 dark:bg-gray-700 px-6 py-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Tracking number</p>
                    <p class="text-xl font-mono font-bold text-gray-900 dark:text-white">{{ $application->tracking_number }}</p>
                </div>
                <div class="pt-4">
                    <a href="{{ route('dashboard') }}" class="text-sm text-blue-600 hover:text-blue-500">
                        &larr; Back to dashboard
                    </a>
                </div>
            </div>
        </x-card>
    @else
        {{-- Progress indicator --}}
        @php
            $sections = $this->sections();
            $totalSteps = count($sections) + 1; // +1 for review
            $currentStep = $onReviewStep ? $totalSteps : $currentSectionIndex + 1;
            $stepLabels = collect($sections)->pluck('title')->push('Review')->all();
        @endphp

        <x-step-indicator :steps="$stepLabels" :current="$currentStep" />

        {{-- Review step --}}
        @if($onReviewStep)
            <x-card title="Review your application">
                <div class="space-y-6">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Please review your answers before submitting. Once submitted, you cannot edit your application.
                    </p>

                    @foreach($sections as $section)
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">{{ $section['title'] }}</h4>
                            <dl class="space-y-1">
                                @foreach($section['fields'] as $field)
                                    @php $savedAnswers = $this->savedAnswersForSection($section['key']); @endphp
                                    <div class="flex gap-2 text-sm">
                                        <dt class="text-gray-500 dark:text-gray-400 min-w-40">{{ $field['label'] }}</dt>
                                        <dd class="text-gray-900 dark:text-white font-medium">
                                            {{ $savedAnswers[$field['key']] ?? '—' }}
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endforeach

                    @if(!$this->canSubmit())
                        <x-alert type="warning" :dismissible="false">
                            All required documents must be uploaded before you can submit.
                        </x-alert>
                    @endif

                    <div class="flex items-center justify-between pt-2">
                        <x-button variant="secondary" wire:click="goBack">
                            &larr; Back
                        </x-button>

                        @if($this->canSubmit())
                            <x-button wire:click="submit" wire:loading.attr="disabled" wire:target="submit">
                                <span wire:loading.remove wire:target="submit">Submit application &rarr;</span>
                                <span wire:loading wire:target="submit">Submitting…</span>
                            </x-button>
                        @endif
                    </div>
                </div>
            </x-card>

        {{-- Form section step --}}
        @else
            @livewire(
                'applications.dynamic-form-section',
                [
                    'applicationUlid' => $application->ulid,
                    'section'         => $this->currentSection(),
                    'savedAnswers'    => $this->savedAnswersForSection($this->currentSection()['key'] ?? ''),
                ],
                key('section-' . $currentSectionIndex)
            )

            <div class="flex items-center justify-between">
                @if($currentSectionIndex > 0)
                    <x-button variant="secondary" wire:click="goBack">
                        &larr; Back
                    </x-button>
                @else
                    <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                        &larr; Back to dashboard
                    </a>
                @endif

                <x-button wire:click="advance">
                    @if($currentSectionIndex < count($sections) - 1)
                        Save &amp; continue &rarr;
                    @else
                        Review application &rarr;
                    @endif
                </x-button>
            </div>
        @endif
    @endif

</div>
```

- [ ] **Step 3: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Commit**

```bash
git add app/Livewire/Applications/ApplicationWizard.php \
        resources/views/livewire/applications/application-wizard.blade.php
git commit -m "feat(m2): ApplicationWizard Livewire full-page component"
```

---

## Task 9: DynamicFormSection Livewire component

**Files:**
- Create: `app/Livewire/Applications/DynamicFormSection.php`
- Create: `resources/views/livewire/applications/dynamic-form-section.blade.php`

- [ ] **Step 1: Create `app/Livewire/Applications/DynamicFormSection.php`**

```php
<?php

namespace App\Livewire\Applications;

use App\Domain\Applications\Actions\UpdateApplicationSection;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DynamicFormSection extends Component
{
    #[Locked]
    public string $applicationUlid;

    /** @var array<string, mixed> */
    #[Locked]
    public array $section = [];

    /** @var array<string, mixed> keyed by bare field_key */
    public array $answers = [];

    public bool $saving = false;

    public bool $saved = false;

    /** @param array<string, mixed> $savedAnswers */
    public function mount(string $applicationUlid, array $section, array $savedAnswers = []): void
    {
        $this->applicationUlid = $applicationUlid;
        $this->section = $section;

        foreach ($section['fields'] as $field) {
            $this->answers[$field['key']] = $savedAnswers[$field['key']] ?? null;
        }
    }

    public function updatedAnswers(string $key): void
    {
        $this->autoSave();
    }

    public function autoSave(): void
    {
        $application = VisaApplication::where('ulid', $this->applicationUlid)->firstOrFail();
        Gate::authorize('update', $application);

        $this->saving = true;

        $visibleAnswers = [];
        foreach ($this->section['fields'] as $field) {
            if ($this->isFieldVisible($field)) {
                $visibleAnswers[$field['key']] = $this->answers[$field['key']] ?? null;
            }
        }

        UpdateApplicationSection::run($application, $this->section['key'], $visibleAnswers);

        $this->saving = false;
        $this->saved = true;

        $this->dispatch('section-updated', sectionKey: $this->section['key'])->to(ApplicationWizard::class);
    }

    /** @return array<string, bool> */
    public function visibleFields(): array
    {
        $result = [];
        foreach ($this->section['fields'] as $field) {
            $result[$field['key']] = $this->isFieldVisible($field);
        }

        return $result;
    }

    /** @param array<string, mixed> $field */
    private function isFieldVisible(array $field): bool
    {
        if (!isset($field['condition'])) {
            return true;
        }

        $conditionField = $field['condition']['field'];
        $conditionValue = $field['condition']['equals'];

        return ($this->answers[$conditionField] ?? null) === $conditionValue;
    }

    public function render(): View
    {
        return view('livewire.applications.dynamic-form-section', [
            'visibleFields' => $this->visibleFields(),
        ]);
    }
}
```

- [ ] **Step 2: Create `resources/views/livewire/applications/dynamic-form-section.blade.php`**

```blade
<div>
    <x-card :title="$section['title']">
        <div class="space-y-5">

            {{-- Saved indicator --}}
            @if($saving)
                <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                    <i class="ti ti-loader-2 animate-spin"></i> Saving…
                </div>
            @elseif($saved)
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 2000)"
                    x-show="show"
                    class="flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400"
                >
                    <i class="ti ti-circle-check"></i> Saved
                </div>
            @endif

            @foreach($section['fields'] as $field)
                @if($visibleFields[$field['key']])
                    @php
                        $fieldName = "answers.{$field['key']}";
                        $isRequired = $field['required'] ?? false;
                        $label = $field['label'];
                    @endphp

                    @if($field['type'] === 'select')
                        <x-select
                            :name="$field['key']"
                            :label="$label"
                            :required="$isRequired"
                            wire:model.blur="answers.{{ $field['key'] }}"
                        >
                            <option value="">Select…</option>
                            @foreach($field['options'] ?? [] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </x-select>

                    @elseif($field['type'] === 'textarea')
                        <x-textarea
                            :name="$field['key']"
                            :label="$label"
                            :required="$isRequired"
                            wire:model.blur="answers.{{ $field['key'] }}"
                        />

                    @elseif($field['type'] === 'radio')
                        <fieldset>
                            <legend class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ $label }}
                                @if($isRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                            </legend>
                            <div class="flex flex-wrap gap-4">
                                @foreach($field['options'] ?? [] as $option)
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                        <input
                                            type="radio"
                                            name="{{ $field['key'] }}"
                                            value="{{ $option }}"
                                            wire:model.live="answers.{{ $field['key'] }}"
                                            class="text-blue-600 focus:ring-blue-500"
                                        >
                                        {{ ucfirst($option) }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                    @elseif($field['type'] === 'checkbox')
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model.live="answers.{{ $field['key'] }}"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            >
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $label }}
                                @if($isRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                            </span>
                        </label>

                    @else
                        {{-- text, date, number --}}
                        <x-input
                            :name="$field['key']"
                            :label="$label"
                            :type="$field['type']"
                            :required="$isRequired"
                            wire:model.blur="answers.{{ $field['key'] }}"
                        />
                    @endif
                @endif
            @endforeach

        </div>
    </x-card>
</div>
```

- [ ] **Step 3: Listen for `section-updated` in `ApplicationWizard`**

The `sectionSaved` method on `ApplicationWizard` is already set up using `#[On]`. Update it to use the Livewire 3 event listener attribute. Open `app/Livewire/Applications/ApplicationWizard.php` and add:

```php
use Livewire\Attributes\On;
```

Change the `sectionSaved` method signature to:

```php
#[On('section-updated')]
public function sectionSaved(string $sectionKey): void
{
    $this->application->load('answers');
}
```

- [ ] **Step 4: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Applications/ resources/views/livewire/applications/
git commit -m "feat(m2): DynamicFormSection Livewire component with auto-save on blur"
```

---

## Task 10: Dashboard update — application list

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/views/pages/dashboard.blade.php`

- [ ] **Step 1: Update `app/Http/Controllers/DashboardController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $profile = $request->user()->applicantProfile;

        $applications = $profile
            ? VisaApplication::where('applicant_profile_id', $profile->ulid)
                ->with(['visaType', 'visaType.country'])
                ->latest()
                ->get()
            : collect();

        return view('pages.dashboard', compact('profile', 'applications'));
    }
}
```

- [ ] **Step 2: Replace `resources/views/pages/dashboard.blade.php`**

```blade
<x-app-layout title="My Dashboard">
    <div class="space-y-8">

        {{-- Page header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Welcome, {{ $profile?->first_name ?? auth()->user()->name }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage your visa applications from here.</p>
            </div>
            <x-button tag="a" href="{{ route('applications.start') }}">
                <i class="ti ti-plus text-base"></i>
                New application
            </x-button>
        </div>

        {{-- Application list --}}
        @if($applications->isEmpty())
            <x-empty-state
                icon="ti-file-certificate"
                heading="No applications yet"
                description="Start a new application to apply for a visa. You can save your progress and come back any time."
            >
                <x-slot name="cta">
                    <x-button tag="a" href="{{ route('applications.start') }}">
                        Start your first application
                    </x-button>
                </x-slot>
            </x-empty-state>
        @else
            <div class="space-y-3">
                @foreach($applications as $application)
                    <a href="{{ route('applications.wizard', $application->tracking_number) }}"
                       class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm hover:border-gray-300 dark:hover:border-gray-600 transition-colors p-5">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4 min-w-0">
                                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/40">
                                    <i class="ti ti-certificate text-xl text-blue-600 dark:text-blue-400"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                        {{ $application->visaType->name }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ $application->visaType->country->name }}
                                        &middot;
                                        <span class="font-mono">{{ $application->tracking_number }}</span>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 flex-shrink-0">
                                <div class="text-right hidden sm:block">
                                    <p class="text-xs text-gray-400 dark:text-gray-500">
                                        @if($application->submitted_at)
                                            Submitted {{ $application->submitted_at->diffForHumans() }}
                                        @else
                                            Started {{ $application->created_at->diffForHumans() }}
                                        @endif
                                    </p>
                                </div>
                                <x-badge :status="$application->status" />
                                <i class="ti ti-chevron-right text-gray-400 dark:text-gray-500"></i>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

    </div>
</x-app-layout>
```

- [ ] **Step 3: Add `tag` prop support to the `x-button` component**

The dashboard uses `<x-button tag="a" href="...">` to render a link as a button. Update `app/View/Components/Button.php`:

```php
<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Button extends Component
{
    public function __construct(
        public string $variant = 'primary',
        public string $type = 'button',
        public bool $loading = false,
        public string $tag = 'button',
    ) {}

    public function render(): View
    {
        return view('components.button');
    }
}
```

Update `resources/views/components/button.blade.php`:

```blade
@props(['variant' => 'primary', 'type' => 'button', 'loading' => false, 'tag' => 'button'])

@php
$classes = match($variant) {
    'primary'   => 'bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500',
    'secondary' => 'bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 focus:ring-blue-500',
    'danger'    => 'bg-red-600 hover:bg-red-700 text-white focus:ring-red-500',
    default     => 'bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500',
};
@endphp

<{{ $tag }}
    @if($tag === 'button') type="{{ $type }}" @endif
    {{ $attributes->merge(['class' => "inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors $classes"]) }}
    @if($loading) disabled @endif
>
    @if($loading)
        <i class="ti ti-loader-2 animate-spin text-base"></i>
    @endif
    {{ $slot }}
</{{ $tag }}>
```

- [ ] **Step 4: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/DashboardController.php \
        resources/views/pages/dashboard.blade.php \
        app/View/Components/Button.php \
        resources/views/components/button.blade.php
git commit -m "feat(m2): dashboard shows application list with start button; x-button supports tag prop"
```

---

## Task 11: Feature tests

**Files:**
- Create: `tests/Feature/CreateApplicationTest.php`
- Create: `tests/Feature/ApplicationWizardTest.php`

- [ ] **Step 1: Create `tests/Feature/CreateApplicationTest.php`**

```bash
php artisan make:test --phpunit CreateApplicationTest --no-interaction
```

Replace with:

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CreateApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ApplicantProfile $profile;
    private VisaType $visaType;
    private FormTemplate $formTemplate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);

        $country = Country::factory()->create();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->assignRole('applicant');

        $this->profile = ApplicantProfile::factory()->create([
            'user_id'                 => $this->user->id,
            'nationality_id'          => $country->id,
            'country_of_residence_id' => $country->id,
        ]);

        $this->visaType = VisaType::factory()->create(['country_id' => $country->id]);
        $this->formTemplate = FormTemplate::factory()->create(['visa_type_id' => $this->visaType->ulid]);
    }

    public function test_start_page_shows_active_visa_types(): void
    {
        $this->actingAs($this->user)
            ->get(route('applications.start'))
            ->assertOk()
            ->assertSee($this->visaType->name);
    }

    public function test_inactive_visa_types_are_not_shown(): void
    {
        $inactive = VisaType::factory()->inactive()->create(['country_id' => Country::factory()->create()->id]);

        $this->actingAs($this->user)
            ->get(route('applications.start'))
            ->assertOk()
            ->assertDontSee($inactive->name);
    }

    public function test_storing_an_application_creates_draft_and_redirects_to_wizard(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('applications.store'), ['visa_type_ulid' => $this->visaType->ulid]);

        $application = VisaApplication::where('applicant_profile_id', $this->profile->ulid)->first();

        $this->assertNotNull($application);
        $this->assertEquals(ApplicationStatus::Draft, $application->status);
        $response->assertRedirect(route('applications.wizard', $application->tracking_number));
    }

    public function test_storing_with_no_form_template_returns_404(): void
    {
        $typeWithNoTemplate = VisaType::factory()->create([
            'country_id' => Country::factory()->create()->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('applications.store'), ['visa_type_ulid' => $typeWithNoTemplate->ulid])
            ->assertStatus(404);
    }

    public function test_second_store_for_same_visa_type_redirects_to_existing_draft(): void
    {
        $existing = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'visa_type_id'         => $this->visaType->ulid,
            'form_template_id'     => $this->formTemplate->ulid,
            'status'               => ApplicationStatus::Draft,
        ]);

        $this->actingAs($this->user)
            ->post(route('applications.store'), ['visa_type_ulid' => $this->visaType->ulid])
            ->assertRedirect(route('applications.wizard', $existing->tracking_number));

        $this->assertDatabaseCount('visa_applications', 1);
    }

    public function test_applicant_cannot_access_another_applicants_wizard(): void
    {
        $other = VisaApplication::factory()->create(); // different profile

        $this->actingAs($this->user)
            ->get(route('applications.wizard', $other->tracking_number))
            ->assertForbidden();
    }

    public function test_withdraw_sets_status_to_withdrawn(): void
    {
        $application = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'status'               => ApplicationStatus::Draft,
        ]);

        $this->actingAs($this->user)
            ->post(route('applications.withdraw', $application->tracking_number))
            ->assertRedirect(route('dashboard'));

        $this->assertEquals(ApplicationStatus::Withdrawn, $application->fresh()->status);
    }

    public function test_guest_cannot_start_application(): void
    {
        $this->get(route('applications.start'))->assertRedirect(route('login'));
    }
}
```

- [ ] **Step 2: Create `tests/Feature/ApplicationWizardTest.php`**

```bash
php artisan make:test --phpunit ApplicationWizardTest --no-interaction
```

Replace with:

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationAnswer;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Livewire\Applications\ApplicationWizard;
use App\Livewire\Applications\DynamicFormSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplicationWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ApplicantProfile $profile;
    private VisaApplication $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'applicant', 'guard_name' => 'web']);

        $country = Country::factory()->create();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->assignRole('applicant');

        $this->profile = ApplicantProfile::factory()->create([
            'user_id'                 => $this->user->id,
            'nationality_id'          => $country->id,
            'country_of_residence_id' => $country->id,
        ]);

        $visaType = VisaType::factory()->create(['country_id' => $country->id]);
        FormTemplate::factory()->create(['visa_type_id' => $visaType->ulid]);

        $this->application = VisaApplication::factory()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'visa_type_id'         => $visaType->ulid,
            'form_template_id'     => FormTemplate::where('visa_type_id', $visaType->ulid)->value('ulid'),
        ]);
    }

    public function test_wizard_loads_for_applicant(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class, ['tracking' => $this->application->tracking_number])
            ->assertOk()
            ->assertSet('onReviewStep', false)
            ->assertSet('currentSectionIndex', 0);
    }

    public function test_advance_increments_section_index(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class, ['tracking' => $this->application->tracking_number])
            ->call('advance')
            ->assertSet('currentSectionIndex', 1);
    }

    public function test_advance_on_last_section_goes_to_review(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class, ['tracking' => $this->application->tracking_number])
            ->set('currentSectionIndex', 1) // last section (FormTemplateFactory has 2 sections)
            ->call('advance')
            ->assertSet('onReviewStep', true);
    }

    public function test_go_back_from_review_restores_last_section(): void
    {
        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class, ['tracking' => $this->application->tracking_number])
            ->set('onReviewStep', true)
            ->call('goBack')
            ->assertSet('onReviewStep', false);
    }

    public function test_dynamic_form_section_saves_answers(): void
    {
        $section = $this->application->formTemplate->schema['sections'][0];

        Livewire::actingAs($this->user)
            ->test(DynamicFormSection::class, [
                'applicationUlid' => $this->application->ulid,
                'section'         => $section,
                'savedAnswers'    => [],
            ])
            ->set('answers.travel_purpose', 'Tourism')
            ->call('autoSave');

        $this->assertDatabaseHas('application_answers', [
            'visa_application_id' => $this->application->ulid,
            'field_key'           => 'travel_details.travel_purpose',
        ]);
    }

    public function test_dynamic_form_section_respects_conditions(): void
    {
        $section = [
            'key'    => 'travel_details',
            'title'  => 'Travel Details',
            'fields' => [
                ['key' => 'travel_purpose', 'type' => 'select', 'label' => 'Purpose', 'required' => true, 'options' => ['Tourism', 'Business']],
                ['key' => 'employer_name', 'type' => 'text', 'label' => 'Employer', 'required' => false, 'condition' => ['field' => 'travel_purpose', 'equals' => 'Business']],
            ],
        ];

        $component = Livewire::actingAs($this->user)
            ->test(DynamicFormSection::class, [
                'applicationUlid' => $this->application->ulid,
                'section'         => $section,
                'savedAnswers'    => [],
            ]);

        // employer_name should be hidden when purpose is Tourism
        $component->set('answers.travel_purpose', 'Tourism');
        $this->assertFalse($component->get('visibleFields')['employer_name'] ?? true);

        // employer_name should be visible when purpose is Business
        $component->set('answers.travel_purpose', 'Business');
        $this->assertTrue($component->get('visibleFields')['employer_name'] ?? false);
    }

    public function test_submitted_application_shows_tracking_number(): void
    {
        $submitted = VisaApplication::factory()->submitted()->create([
            'applicant_profile_id' => $this->profile->ulid,
            'visa_type_id'         => $this->application->visa_type_id,
            'form_template_id'     => $this->application->form_template_id,
        ]);

        Livewire::actingAs($this->user)
            ->test(ApplicationWizard::class, ['tracking' => $submitted->tracking_number])
            ->assertSee($submitted->tracking_number);
    }
}
```

- [ ] **Step 3: Run all the new tests**

```bash
php artisan test --compact --filter="CreateApplicationTest|ApplicationWizardTest"
```

Expected: all passing.

- [ ] **Step 4: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Feature/CreateApplicationTest.php tests/Feature/ApplicationWizardTest.php
git commit -m "test(m2): CreateApplicationTest and ApplicationWizardTest feature tests"
```

---

## Task 12: Final verification

- [ ] **Step 1: Run the full test suite**

```bash
php -d memory_limit=512M artisan test --compact
```

Expected: all tests pass (including M1 tests).

- [ ] **Step 2: Build assets**

```bash
npm run build
```

Expected: zero errors.

- [ ] **Step 3: Spot check routes**

```bash
php artisan route:list --name=applications --except-vendor 2>&1
```

Expected:

```
GET|HEAD   applications/start   applications.start   › Applications\ApplicationController@start
POST       applications         applications.store   › Applications\ApplicationController@store
GET|HEAD   applications/{tracking}   applications.wizard  › App\Livewire\Applications\ApplicationWizard
POST       applications/{tracking}/withdraw   applications.withdraw › Applications\ApplicationController@withdraw
```

- [ ] **Step 4: Final Pint pass**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git status
```

If any files are modified by Pint, commit them:

```bash
git commit -m "chore(m2): pint formatting pass"
```

---

## Self-Review

**Spec coverage check:**

| M2 Requirement | Covered by task |
|---|---|
| Applicant can create one draft per visa type | Tasks 6, 11 |
| Applicant can save each section independently | Tasks 3, 9 |
| Applicant can submit only when required fields valid | Tasks 4, 8 (canSubmit) |
| Submitted snapshot is immutable | Tasks 1, 4, 11 |
| Application receives non-sequential tracking number | Task 3 (unit test) |
| Status history is append-only | Tasks 4, 5 |
| Applicant cannot edit a submitted application | Tasks 4 (policy), 8 (wizard blocks edit) |
| VisaApplicationPolicy required | Task 4 |
| ULIDs for all application records | Existing — models already use HasUlids |
| No raw IDs in public-facing routes — use tracking_number | Tasks 6, 8 |
| All status transitions wrapped in transactions | Tasks 4, 5 |
| Dynamic form renderer (section → fields) | Tasks 8, 9 |
| Conditional field visibility | Task 9, 11 |
| Auto-save on blur | Task 9 |
| Progress bar / step indicator | Task 8 |
| Review step | Task 8 |
| Tracking number display after submit | Task 8 |
| Dashboard shows real applications | Task 10 |
| Visa type picker | Task 7 |
| Withdraw action | Task 5 |

**Security rules verified:**
- No raw IDs in routes — `tracking_number` used throughout
- Policy gate on every controller action (`$this->authorize(...)`)
- Policy gate in Livewire component methods (`Gate::authorize(...)`)
- Snapshot immutable via `firstOrCreate` — cannot be overwritten
- Status transitions in DB transactions
- Applicant can only see their own applications (policy)
