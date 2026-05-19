# Visa Application System — Full Workflow, Milestone Plan & Panel Database Design

> This document is the canonical implementation reference for the complete backend,
> officer panel, and admin panel of the Visa Application System.
> It synthesises the architecture blueprint, skill file, and milestone plan into one
> actionable specification for Claude Code and engineering collaborators.
>
> **Read `CLAUDE.md` before implementing anything in this document.**
> All business logic lives in `app/Domain/`. Filament panels are thin wrappers.
> Every custom Filament action requires explicit `->authorize()`.

---

## Table of Contents

1. [System overview](#1-system-overview)
2. [Full application workflow](#2-full-application-workflow)
3. [Backend milestone plan](#3-backend-milestone-plan)
4. [Core database schema](#4-core-database-schema)
5. [Officer panel — database design & Filament 4](#5-officer-panel--database-design--filament-4)
6. [Admin panel — database design & Filament 4](#6-admin-panel--database-design--filament-4)
7. [Shared domain actions](#7-shared-domain-actions)
8. [Queue configuration](#8-queue-configuration)
9. [Security rules](#9-security-rules)
10. [Testing strategy](#10-testing-strategy)
11. [Implementation checklist](#11-implementation-checklist)

---

## 1. System overview

### Architecture

A **modular Laravel 12 monolith** with clean domain boundaries. One deployable application.
Extraction into services is deferred until load or compliance requires it.

```
Applicant portal     →  Blade + Livewire 3        (/dashboard, /applications)
Officer panel        →  Filament 4 panel           (/officer)
Admin panel          →  Filament 4 panel           (/admin)
Public tracking      →  Plain Laravel route        (/track)
Webhook endpoints    →  Laravel route, no session  (/webhooks/stripe)
```

### Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 12, PHP 8.3+ |
| Database | PostgreSQL — `jsonb` for schema/answer columns |
| Cache & queues | Redis + Laravel Horizon |
| Panels | Filament 4 — two separate PanelProviders |
| Applicant portal | Blade + Livewire 3 |
| RBAC | `spatie/laravel-permission` + Laravel Policies |
| Audit | `spatie/laravel-activitylog` + custom `audit_logs` table |
| Payments | Stripe Checkout via `laravel/cashier` |
| File storage | Private S3-compatible disk — never public |
| Primary keys | ULIDs for all sensitive models |
| DTOs | `spatie/laravel-data` |
| PDF | `barryvdh/laravel-dompdf` |
| Tests | Pest |
| Code style | Laravel Pint |

### Canonical roles

```
applicant  ·  agent  ·  case_officer  ·  senior_officer
document_verifier  ·  finance_officer  ·  support_staff  ·  admin  ·  super_admin
```

---

## 2. Full application workflow

### 2.1 Lifecycle state machine

```
[Admin] Create visa type → fee rules → form template → document requirements
           ↓
[Applicant] Register → verify email → complete profile
           ↓
[Applicant] Select visa type → fill form sections (auto-save) → upload documents
           ↓
[Applicant] Review → submit → pay (Stripe Checkout)
           ↓
[Webhook]  PaymentSucceeded → mark application paid → generate receipt PDF → notify
           ↓
[System]   Assign to officer queue
           ↓
[Officer]  Review answers → verify documents (accept / reject)
           ↓
[Officer]  Option A: Request additional information
             → Applicant corrects + resubmits → back to officer review
           Option B: Schedule appointment
             → Appointment letter PDF generated → applicant notified
           Option C: Approve → decision letter PDF → notify applicant
           Option D: Reject → reason recorded → notify applicant
           ↓
[All]      Every action written to audit_logs
```

### 2.2 ApplicationStatus enum (canonical)

```php
enum ApplicationStatus: string
{
    case Draft                = 'draft';
    case Submitted            = 'submitted';
    case PaymentPending       = 'payment_pending';
    case Paid                 = 'paid';
    case UnderReview          = 'under_review';
    case InfoRequested        = 'info_requested';
    case Resubmitted          = 'resubmitted';
    case DocumentVerification = 'document_verification';
    case InterviewScheduled   = 'interview_scheduled';
    case DecisionPending      = 'decision_pending';
    case Approved             = 'approved';
    case Rejected             = 'rejected';
    case Withdrawn            = 'withdrawn';
    case Closed               = 'closed';
}
```

### 2.3 Public status mapping

| Internal status | Public label | Applicant action |
|---|---|---|
| `draft` | Draft | Complete and submit |
| `submitted` / `payment_pending` | Submitted | Pay or wait |
| `paid` / `under_review` / `document_verification` | In review | No action |
| `info_requested` | Action required | Correct and resubmit |
| `interview_scheduled` | Appointment scheduled | Attend appointment |
| `approved` | Approved | Download decision letter |
| `rejected` | Not approved | View reason |
| `closed` / `withdrawn` | Closed | No further action |

### 2.4 Tracking number format

```
VISA-{ISO2}-{YEAR}-{6 random uppercase alphanumeric}
Example: VISA-IND-2026-8K3F2Q
```

Never sequential. Never expose the ULID primary key in public URLs or emails.

### 2.5 Event-driven side effects

```
ApplicationSubmitted   → GenerateApplicationSummaryPdf
                       → NotifyApplicantApplicationSubmitted
                       → NotifyOfficerQueue
                       → CreateAuditLog

PaymentSucceeded       → MarkApplicationPaid
                       → GenerateReceiptPdf
                       → NotifyApplicantPaymentReceived
                       → NotifyFinanceTeam
                       → CreateLedgerEntry

DocumentRejected       → MoveApplicationToInfoRequested
                       → NotifyApplicantDocumentRejected
                       → CreateAuditLog

ApplicationApproved    → GenerateDecisionLetterPdf
                       → NotifyApplicantApproved
                       → CreateAuditLog

ApplicationRejected    → NotifyApplicantRejected
                       → CreateAuditLog
```

---

## 3. Backend milestone plan

### Milestone 0 — Foundation

**Goal:** Working skeleton. Nothing functional yet.

Deliverables:
- Laravel 12 project with domain folder scaffold at `app/Domain/`
- Filament 4 installed with `AdminPanelProvider` (`/admin`) and `OfficerPanelProvider` (`/officer`)
- PostgreSQL + Redis connections configured
- Horizon configured with named queues: `high`, `default`, `emails`, `documents`, `pdfs`, `reports`
- Pest + Pint installed and configured
- `CLAUDE.md` at project root
- `docs/` folder scaffolded

Acceptance:
- `php artisan test` passes
- `php artisan pint --test` passes
- `GET /admin` → 302 to login
- `GET /officer` → 302 to login
- `php artisan horizon` starts without errors

---

### Milestone 1 — Identity, Roles & Base Admin

**Goal:** Users, roles, permissions, and configuration resources working. Authorization proven.

Tables: `users`, `applicant_profiles`, `countries`, `visa_types`, `visa_fees`

Domain code:
- `Identity/Models/` — `User`, `ApplicantProfile`
- `Identity/Actions/` — `CreateApplicantProfile`, `UpdateApplicantProfile`, `SuspendUser`, `AssignRole`
- `Identity/Policies/` — `UserPolicy`, `ApplicantProfilePolicy`

Filament resources (admin panel):
- `UserResource`, `ApplicantProfileResource`, `CountryResource`, `VisaTypeResource`, `VisaFeeResource`

Roles to seed:
```
applicant  agent  case_officer  senior_officer  document_verifier
finance_officer  support_staff  admin  super_admin
```

Acceptance:
- Admins can access `/admin`. Applicants cannot.
- Officers can access `/officer`. Applicants cannot.
- Every model has a Policy with a passing policy test.
- Cross-user data isolation confirmed.

---

### Milestone 2 — Application Workflow & Dynamic Forms

**Goal:** Applicant can create, save, and submit a draft application.

Tables: `form_templates`, `visa_applications`, `application_answers`,
`application_snapshots`, `application_status_histories`

Domain code:
- `Applications/Actions/` — `CreateDraftApplication`, `UpdateApplicationSection`,
  `SubmitApplication`, `GenerateTrackingNumber`
- `Applications/Enums/ApplicationStatus`
- `Applications/Models/` — `VisaApplication`, `ApplicationAnswer`,
  `ApplicationSnapshot`, `ApplicationStatusHistory`, `FormTemplate`
- `Applications/Policies/VisaApplicationPolicy`

Key constraints:
- Snapshot is immutable once created at submission
- Status history is append-only
- Tracking number is non-sequential (format above)
- Raw database IDs never in public URLs

Acceptance:
- Draft → section saves → submit flow works end-to-end
- `application_snapshots` row cannot be updated after creation
- Duplicate submission is rejected

---

### Milestone 3 — Document Management

**Goal:** Secure, versioned, audited document uploads.

Tables: `document_types`, `visa_type_document_requirements`,
`application_documents`, `document_versions`

Domain code:
- `Documents/Actions/` — `UploadDocumentVersion`, `AcceptDocument`, `RejectDocument`
- `Documents/Jobs/ScanDocumentJob`
- `Documents/Models/` — `DocumentType`, `ApplicationDocument`, `DocumentVersion`
- `Documents/Policies/ApplicationDocumentPolicy`

Storage rules:
- Private disk only — never public
- ULID-based object paths
- SHA-256 checksum stored on `document_versions`
- Original filename stored as metadata only
- Virus scan queued after every upload
- Preview/download blocked until `virus_scan_status = clean`

Acceptance:
- Required documents enforced before submission
- Replacement upload creates new `document_versions` row
- Every upload, preview, download, accept, reject written to `audit_logs`
- Unauthorized users cannot access documents

---

### Milestone 4 — Payments & Invoices

**Goal:** Stripe Checkout to paid state, idempotent webhooks, receipt PDF.

Tables: `payments`, `payment_items`, `payment_webhook_events`, `invoices`

Domain code:
- `Payments/Actions/` — `CalculateApplicationFee`, `CreateCheckoutSession`,
  `HandlePaymentWebhook`, `GenerateReceiptPdf`
- `Payments/Models/` — `Payment`, `PaymentItem`, `PaymentWebhookEvent`, `Invoice`
- `Payments/Webhooks/StripeWebhookController`

Webhook idempotency pattern:
```php
DB::transaction(function () use ($event) {
    $row = PaymentWebhookEvent::firstOrCreate(
        ['provider' => 'stripe', 'provider_event_id' => $event->id],
        ['event_type' => $event->type, 'payload' => $event->toArray()]
    );
    if ($row->processed_at !== null) return;
    // resolve payment, transition application, generate receipt, mark processed_at
    $row->update(['processed_at' => now()]);
});
```

Acceptance:
- Duplicate webhook does not duplicate ledger or application transition
- Receipt PDF uses frozen invoice data — never recomputed from live fee rules
- Finance role can view payments but cannot alter applications

---

### Milestone 5 — Officer Review & Decisions

**Goal:** Officers work their full queue in Filament with complete audit trails.

Filament `VisaApplicationResource` (officer panel):
- Table filters: status, visa type, country, assigned officer, date range
- Actions: assign, request info, schedule appointment, approve, reject
- Relation managers: documents, payments, review notes, status history
- Document lightbox: serves files through signed URL → controller → policy → audit log

Domain code:
- `Applications/Actions/` — `AssignApplicationToOfficer`,
  `RequestAdditionalInformation`, `ScheduleAppointment`,
  `ApproveApplication`, `RejectApplication`

Guards:
- Officer sees only `assigned_to_user_id = auth()->id()` (senior_officer + admin widen scope)
- Approval blocked if required docs are missing, infected, pending, or rejected
- Every decision records actor, timestamp, from/to status, reason

Acceptance:
- Officer cannot approve an unassigned application (unless senior_officer policy allows)
- Document lightbox logs every preview and download to `audit_logs`
- Request-info action stores `unlocked_fields` in `review_notes.metadata`

---

### Milestone 6 — Notifications, PDFs & Queues

**Goal:** All lifecycle notifications queued. All PDFs generated from immutable data.

Deliverables:
- Database + email notifications for all applicant-facing lifecycle events
- Queued PDF jobs: receipt, appointment letter, decision letter, application summary
- All jobs dispatched to named queues (never inline in HTTP)
- PDF Blade templates receive DTOs — zero database queries inside templates

Acceptance:
- Submit → notification + PDF jobs dispatched (queue worker processes them)
- Failed jobs visible in Horizon and retryable
- Zero slow work blocks HTTP requests

---

### Milestone 7 — Reporting, Exports & Hardening

**Goal:** Pre-aggregated dashboards. Queued exports. Security hardened.

Read model tables (written nightly by `GenerateDailyMetricsJob` on `reports` queue):
- `daily_application_metrics`
- `daily_payment_metrics`
- `officer_performance_metrics`
- `document_rejection_metrics`

Deliverables:
- Filament dashboard widgets reading from read models — no live aggregation
- Queued CSV/XLSX exports to private storage with 24-hour expiry
- Export access authorized, redacted, and audited
- Rate limits on login, tracking, upload, OTP, webhook retry
- MFA enforced for officer, finance, admin, super_admin roles
- `APP_DEBUG=false`, HTTPS enforced, production checklist complete

Acceptance:
- Large export does not block the web server
- `php artisan test` — all tests green including policy + security tests
- `php artisan pint --test` — zero style violations

---

## 4. Core database schema

### 4.1 `users`

| Column | Type | Notes |
|---|---|---|
| `id` | `ulid` PK | Never in public URLs |
| `name` | `string` | Display name |
| `email` | `string` unique | Login + notifications |
| `email_verified_at` | `timestamp` nullable | Required before submission |
| `password` | `string` | Bcrypt/Argon2 via Hash facade |
| `phone` | `string` nullable | Appointment reminders |
| `status` | `enum` | `active`, `suspended`, `pending` |
| `last_login_at` | `timestamp` nullable | Login audit |
| `timestamps` + `soft_deletes` | — | — |

### 4.2 `applicant_profiles`

| Column | Type | Notes |
|---|---|---|
| `id` | `ulid` PK | — |
| `user_id` | FK `users` unique | One-to-one |
| `first_name`, `middle_name`, `last_name` | `string` | Legal name — separate columns |
| `date_of_birth` | `date` | Used for public tracking verification |
| `nationality_country_id` | FK `countries` | Drives fee resolution |
| `passport_number_encrypted` | `text` nullable | Encrypted cast |
| `passport_issued_at`, `passport_expires_at` | `date` nullable | Validity checks |
| `metadata` | `jsonb` | Flexible profile data |
| `timestamps` | — | — |

### 4.3 `countries`

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint` PK | Not sensitive — sequential OK |
| `iso2` | `char(2)` unique | `IN`, `US`, `GB` |
| `iso3` | `char(3)` unique | `IND`, `USA`, `GBR` |
| `name` | `string` | Display name |
| `is_active` | `boolean` | Controls selection lists |

### 4.4 `visa_types`

| Column | Type | Notes |
|---|---|---|
| `id` | `ulid` PK | — |
| `country_id` | FK `countries` | Destination |
| `code` | `string` | `tourist`, `student`, `business`, `transit` |
| `name` | `string` | Display |
| `entry_type` | `enum` | `single`, `multiple`, `transit` |
| `processing_days_min` / `processing_days_max` | `integer` | SLA bounds |
| `max_stay_days` | `integer` | On decision letter |
| `validity_days` | `integer` | `decided_at + validity_days = valid_until` |
| `issuing_authority` | `string` | Decision letter header |
| `conditions_text` | `text` | Legal conditions on decision letter |
| `is_active` | `boolean` | Controls applicant availability |

### 4.5 `visa_fees`

| Column | Type | Notes |
|---|---|---|
| `id` | `ulid` PK | — |
| `visa_type_id` | FK `visa_types` | — |
| `nationality_country_id` | FK `countries` nullable | `null` = all nationalities |
| `currency` | `char(3)` | ISO 4217 |
| `base_fee`, `service_fee`, `priority_fee` | `decimal(12,2)` | Fee components |
| `tax_rate` | `decimal(5,4)` | e.g. `0.1800` for 18% GST |
| `valid_from` | `date` | Inclusive |
| `valid_until` | `date` nullable | `null` = no expiry |
| `is_active` | `boolean` | — |

**Rule:** Fee rows are immutable after creation. To change a fee, create a new row with a new `valid_from`. Never edit existing rows.

### 4.6 `form_templates`

| Column | Type | Notes |
|---|---|---|
| `id` | `ulid` PK | — |
| `visa_type_id` | FK `visa_types` | — |
| `version` | `integer` | Immutable once published |
| `name` | `string` | Admin label |
| `schema` | `jsonb` | Sections, fields, conditions, validation |
| `is_active` | `boolean` | One active per visa type |
| `published_at` | `timestamp` nullable | `null` = draft. Once set, schema is immutable. |

### 4.7 `visa_applications`

| Column | Type | Notes |
|---|---|---|
| `id` | `ulid` PK | — |
| `tracking_number` | `string` unique | Public non-sequential reference |
| `user_id` | FK `users` | Owner account |
| `applicant_profile_id` | FK `applicant_profiles` | — |
| `visa_type_id` | FK `visa_types` | — |
| `form_template_id` | FK `form_templates` | Version used |
| `status` | `enum` (ApplicationStatus) | Internal state |
| `public_status` | `string` | Simplified applicant-facing label |
| `destination_country_id`, `nationality_country_id` | FK `countries` | — |
| `submitted_at`, `paid_at` | `timestamp` nullable | Workflow timestamps |
| `assigned_to_user_id` | FK `users` nullable | Officer assignment |
| `decided_by_user_id`, `decided_at`, `decision` | nullable | Decision metadata |
| `metadata` | `jsonb` | e.g. `valid_until` written at approval time |
| `timestamps` + `soft_deletes` | — | — |

**Required indexes:**
```php
$table->unique('tracking_number');
$table->index(['user_id', 'status']);
$table->index(['status', 'submitted_at']);
$table->index(['assigned_to_user_id', 'status']);
$table->index(['visa_type_id', 'submitted_at']);
```

### 4.8 `application_answers`

| Column | Type | Notes |
|---|---|---|
| `id` | `ulid` PK | — |
| `application_id` | FK `visa_applications` | — |
| `section_key` | `string` | e.g. `travel`, `personal` |
| `field_key` | `string` | e.g. `employer_name` |
| `value` | `jsonb` | Typed answer |
| `encrypted` | `boolean` | True when encrypted before storage |

**Unique constraint:** `(application_id, section_key, field_key)`

### 4.9 `application_status_histories`

| Column | Type | Notes |
|---|---|---|
| `id` | `ulid` PK | — |
| `application_id` | FK `visa_applications` | — |
| `from_status`, `to_status` | `string` | Transition values |
| `actor_user_id` | FK `users` nullable | `null` = system actor |
| `reason` | `text` nullable | Required for overrides/rejections |
| `metadata` | `jsonb` | Transition context |
| `created_at` | `timestamp` | **No `updated_at` — append-only** |

### 4.10 `application_documents` + `document_versions`

```
application_documents
  id  application_id  document_type_id  uploaded_by_user_id
  status  current_version_id  reviewed_by_user_id  reviewed_at
  rejection_reason  timestamps

document_versions
  id  application_document_id  storage_disk  storage_path
  original_filename  mime_type  file_size  sha256_checksum
  version_number  virus_scan_status  timestamps
```

**`virus_scan_status` enum:** `pending`, `scanning`, `clean`, `infected`, `failed`

### 4.11 `payments` + related

```
payments
  id  application_id  user_id  provider  provider_payment_id
  provider_checkout_session_id  currency  amount  status
  failure_reason  paid_at  metadata  timestamps

payment_items
  id  payment_id  type (visa_fee|service_fee|priority_fee|tax)
  description  amount

payment_webhook_events
  id  provider  provider_event_id  event_type  payload
  processed_at  processing_error
  UNIQUE (provider, provider_event_id)

invoices
  id  application_id  payment_id  invoice_number  currency
  subtotal  tax  total  pdf_storage_path  issued_at
```

### 4.12 `audit_logs`

| Column | Type | Notes |
|---|---|---|
| `id` | `ulid` PK | — |
| `actor_user_id` | FK `users` nullable | `null` = system |
| `action` | `string` | `application.approved`, `document.downloaded` |
| `auditable_type`, `auditable_id` | polymorphic | Target record |
| `ip_address`, `user_agent` | nullable | Request context |
| `old_values`, `new_values`, `metadata` | `jsonb` nullable | Change context |
| `created_at` | `timestamp` | **Append-only. No `updated_at`.** |

---

## 5. Officer panel — database design & Filament 4

### 5.1 Panel configuration

```php
// app/Providers/Filament/OfficerPanelProvider.php

class OfficerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('officer')
            ->path('officer')
            ->login()
            ->colors(['primary' => Color::Sky])
            ->authMiddleware([
                Authenticate::class,
                EnsureEmailIsVerified::class,
                EnsureMfaIsEnabled::class,    // MFA required — no exceptions
            ])
            ->authGuard('web')
            ->canAccess(fn () =>
                auth()->user()?->hasAnyRole([
                    'case_officer',
                    'senior_officer',
                    'document_verifier',
                    'finance_officer',
                    'support_staff',
                ])
            )
            ->resources([
                OfficerVisaApplicationResource::class,
                OfficerDocumentReviewResource::class,
                AppointmentResource::class,
            ])
            ->widgets([
                OfficerQueueStatsWidget::class,
                MySlaWidget::class,
                MyPerformanceWidget::class,
            ])
            ->pages([OfficerDashboardPage::class])
            ->navigationGroups([
                NavigationGroup::make('Queue'),
                NavigationGroup::make('Review'),
                NavigationGroup::make('Appointments'),
                NavigationGroup::make('Reports'),
            ]);
    }
}
```

### 5.2 Role capabilities matrix

| Role | My queue | Team queue | Override | Refunds | Reports |
|---|---|---|---|---|---|
| `case_officer` | ✓ (assigned only) | Read only | ✗ | ✗ | Own stats |
| `senior_officer` | ✓ (all team) | ✓ | ✓ (with reason) | ✗ | Team stats |
| `document_verifier` | Document tasks only | ✗ | ✗ | ✗ | ✗ |
| `finance_officer` | Payment view only | ✗ | ✗ | ✓ (approved) | Payment stats |
| `support_staff` | Read + metadata | ✗ | ✗ | ✗ | ✗ |

### 5.3 Tables used by the officer panel

The officer panel **reads** from:
```
visa_applications           application_answers
application_status_histories application_documents
document_versions           document_types
visa_type_document_requirements review_notes
appointments                payments  invoices
audit_logs (own actions)    officer_performance_metrics
```

The officer panel **writes** to (via Domain Actions only):
```
visa_applications           (status transitions only — never direct Eloquent)
application_status_histories (append-only via SubmitApplication, Approve, etc.)
review_notes                (AddReviewNote action)
appointments                (ScheduleAppointment action)
application_documents       (AcceptDocument, RejectDocument actions)
audit_logs                  (every action — via CreateAuditLog)
```

### 5.4 Additional tables owned by the officer workflow

#### `review_notes`

| Column | Type | Notes |
|---|---|---|
| `id` | `ulid` PK | — |
| `application_id` | FK `visa_applications` | — |
| `author_user_id` | FK `users` | Officer or staff |
| `visibility` | `enum` | `internal` or `applicant` |
| `note` | `text` | Content |
| `metadata` | `jsonb` nullable | e.g. `unlocked_fields` array for info requests |
| `timestamps` | — | — |

**Rule:** Notes with `visibility = internal` are **never** returned by applicant-facing API endpoints. Enforced at the query level in `ReviewNotePolicy`.

#### `appointments`

| Column | Type | Notes |
|---|---|---|
| `id` | `ulid` PK | — |
| `application_id` | FK `visa_applications` | — |
| `location_id` | FK `service_locations` | Office or biometrics centre |
| `scheduled_by_user_id` | FK `users` | Officer who scheduled |
| `type` | `enum` | `interview`, `biometrics`, `document_drop` |
| `scheduled_at` | `timestamp` | Appointment time |
| `status` | `enum` | `scheduled`, `completed`, `missed`, `cancelled` |
| `notes_for_applicant` | `text` nullable | Shown in appointment letter |
| `timestamps` | — | — |

#### `service_locations`

| Column | Type | Notes |
|---|---|---|
| `id` | `ulid` PK | — |
| `name` | `string` | e.g. `Mumbai Visa Centre — Bandra` |
| `address` | `text` | Full postal address |
| `city` | `string` | — |
| `country_id` | FK `countries` | — |
| `is_active` | `boolean` | Controls officer appointment dropdown |
| `timestamps` | — | — |

### 5.5 Officer panel Filament resources

#### `OfficerVisaApplicationResource`

**Navigation:** Queue group · icon `ti-list-check`

**Scope enforcement (in Policy, not a removable filter):**
```php
// VisaApplicationPolicy::viewAny() — scoped per role
public function viewAny(User $user): bool
{
    if ($user->hasRole('senior_officer')) {
        // Senior officer sees full team — no assigned_to_user_id filter
        return true;
    }
    // case_officer — query scope adds WHERE assigned_to_user_id = auth()->id()
    return $user->hasAnyRole(['case_officer', 'document_verifier', 'support_staff']);
}
```

**Table configuration:**
```php
->columns([
    TextColumn::make('applicantProfile.full_name')->searchable(),
    TextColumn::make('tracking_number')->copyable()->fontFamily('mono'),
    BadgeColumn::make('status')->color(fn($s) => ApplicationStatus::color($s)),
    TextColumn::make('visaType.name'),
    // SLA column — computed in query scope, not PHP
    TextColumn::make('sla_days_remaining')
        ->label('SLA')
        ->formatStateUsing(fn($s) => $s < 0 ? 'Breached' : $s.'d')
        ->color(fn($s) => match(true) {
            $s < 0  => 'danger',
            $s <= 2 => 'warning',
            default => 'gray',
        }),
    TextColumn::make('assignedTo.name')->label('Officer'),
])
->recordClasses(fn($record) => match(true) {
    $record->sla_days_remaining < 0  => 'border-l-4 border-l-red-500',
    $record->sla_days_remaining <= 2 => 'border-l-4 border-l-amber-500',
    $record->recently_assigned       => 'border-l-4 border-l-purple-500',
    default                          => '',
})
```

**Filters:**
```php
->filters([
    SelectFilter::make('status')->options(ApplicationStatus::class),
    SelectFilter::make('visa_type')->relationship('visaType', 'name'),
    Filter::make('sla_at_risk')->query(fn($q) =>
        $q->whereRaw('processing_days_max - DATE_PART(\'day\', NOW() - submitted_at) <= 2')
    ),
    Filter::make('resubmitted')->query(fn($q) =>
        $q->where('status', ApplicationStatus::Resubmitted)
    ),
    Filter::make('unassigned')->query(fn($q) =>
        $q->whereNull('assigned_to_user_id')
    ),
])
```

**Custom actions (all require `->authorize()`):**
```php
// Assign to self
Action::make('assign_to_self')
    ->authorize(fn($record) =>
        auth()->user()->can('assign', $record)
    )
    ->action(fn($record) =>
        AssignApplicationToOfficer::run($record, auth()->user())
    ),

// Reassign (senior officer only)
Action::make('reassign')
    ->authorize(fn($record) =>
        auth()->user()->hasRole('senior_officer') &&
        auth()->user()->can('reassign', $record)
    )
    ->form([
        Select::make('officer_id')
            ->options(User::role('case_officer')->pluck('name', 'id'))
            ->required()
    ])
    ->action(fn($record, $data) =>
        AssignApplicationToOfficer::run($record, User::find($data['officer_id']))
    ),

// Request additional information
Action::make('request_info')
    ->authorize(fn($record) => auth()->user()->can('requestInfo', $record))
    ->form([
        Textarea::make('message')->required(),
        CheckboxList::make('documents_to_resubmit')
            ->options(fn($record) => $record->documents->pluck('documentType.name', 'id')),
        CheckboxList::make('fields_to_unlock')
            ->options(EditableFields::options())
            ->helperText('Identity fields (name, DOB, passport) cannot be unlocked'),
        Select::make('deadline_days')
            ->options([3 => '3 days', 7 => '7 days (default)', 14 => '14 days'])
            ->default(7),
    ])
    ->action(fn($record, $data) =>
        RequestAdditionalInformation::run($record, $data)
    ),

// Approve
Action::make('approve')
    ->authorize(fn($record) => auth()->user()->can('approve', $record))
    ->disabled(fn($record) => $record->hasUnreviewedRequiredDocuments())
    ->form([
        Select::make('validity_period')
            ->options(['12_months' => '12 months', '6_months' => '6 months'])
            ->default('12_months'),
        Select::make('entry_type')
            ->options(['single' => 'Single', 'multiple' => 'Multiple'])
            ->default('single'),
        Textarea::make('internal_notes')->nullable(),
    ])
    ->requiresConfirmation()
    ->modalDescription('Approving will generate the decision letter PDF and notify the applicant.')
    ->action(fn($record, $data) =>
        ApproveApplication::run($record, auth()->user(), $data)
    ),

// Reject
Action::make('reject')
    ->authorize(fn($record) => auth()->user()->can('reject', $record))
    ->form([
        Select::make('rejection_reason')
            ->options(RejectionReason::options())
            ->required(),
        Textarea::make('applicant_explanation')
            ->label('Explanation for applicant')
            ->required()
            ->minLength(20),
        Textarea::make('internal_notes')->nullable(),
    ])
    ->requiresConfirmation()
    ->action(fn($record, $data) =>
        RejectApplication::run($record, auth()->user(), $data)
    ),

// Schedule appointment
Action::make('schedule_appointment')
    ->authorize(fn($record) => auth()->user()->can('scheduleAppointment', $record))
    ->form([
        Select::make('type')
            ->options(['biometrics' => 'Biometrics', 'interview' => 'Interview',
                       'document_drop' => 'Document drop-off'])
            ->required(),
        Select::make('location_id')
            ->options(ServiceLocation::active()->pluck('name', 'id'))
            ->required(),
        DateTimePicker::make('scheduled_at')->required(),
        Textarea::make('notes_for_applicant')->nullable(),
    ])
    ->action(fn($record, $data) =>
        ScheduleAppointment::run($record, auth()->user(), $data)
    ),
```

**Relation managers:**
```php
->relationManagers([
    ApplicationAnswersRelationManager::class,   // read-only view of answers
    ApplicationDocumentsRelationManager::class, // document review + lightbox
    ReviewNotesRelationManager::class,          // add/view notes (internal/applicant)
    ApplicationStatusHistoryRelationManager::class, // append-only timeline
    PaymentsRelationManager::class,             // read-only payment details
])
```

#### Document lightbox (ApplicationDocumentsRelationManager)

```php
Action::make('preview')
    ->authorize(fn($record) => auth()->user()->can('view', $record))
    ->url(fn($record) => route('officer.documents.preview', $record))
    // Controller checks policy, writes audit_log, returns signed URL
    // NEVER a direct S3 URL

Action::make('accept')
    ->authorize(fn($record) => auth()->user()->can('accept', $record))
    ->action(fn($record) => AcceptDocument::run($record, auth()->user()))
    ->color('success'),

Action::make('reject')
    ->authorize(fn($record) => auth()->user()->can('reject', $record))
    ->form([
        Select::make('rejection_reason')
            ->options(DocumentRejectionReason::options())
            ->required(),
    ])
    ->action(fn($record, $data) =>
        RejectDocument::run($record, auth()->user(), $data['rejection_reason'])
    )
    ->color('danger'),
```

#### `AppointmentResource`

**Navigation:** Appointments group · icon `ti-calendar`

Simple read + update resource. Officers can mark appointments as `completed`, `missed`, or `cancelled`. `ScheduleAppointment` action handles creation.

### 5.6 Officer dashboard widgets

All widgets read from `officer_performance_metrics` — no live COUNT() queries.

```php
// OfficerQueueStatsWidget
protected function getStats(): array
{
    $metrics = OfficerPerformanceMetrics::where('officer_id', auth()->id())
        ->where('date', today())
        ->first();

    return [
        Stat::make('Assigned', $this->myQueue()->count())
            ->description('currently in my queue'),
        Stat::make('Avg. turnaround',
            round(($metrics?->avg_turnaround_hours ?? 0) / 24, 1).'d'),
        Stat::make('SLA at risk',
            $this->myQueue()->slaAtRisk()->count())
            ->color('danger'),
        Stat::make('Resubmissions',
            $this->myQueue()->where('status', ApplicationStatus::Resubmitted)->count()),
    ];
}
```

---

## 6. Admin panel — database design & Filament 4

### 6.1 Panel configuration

```php
// app/Providers/Filament/AdminPanelProvider.php

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors(['primary' => Color::Zinc])
            ->authMiddleware([
                Authenticate::class,
                EnsureEmailIsVerified::class,
                EnsureMfaIsEnabled::class,    // MFA required
            ])
            ->authGuard('web')
            ->canAccess(fn () =>
                auth()->user()?->hasRole('super_admin')
                // Only super_admin. admin role gets read-only subset if needed later.
            )
            ->resources([
                // Identity
                UserResource::class,
                // Configuration
                CountryResource::class,
                VisaTypeResource::class,
                VisaFeeResource::class,
                FormTemplateResource::class,
                DocumentTypeResource::class,
                ServiceLocationResource::class,
                // Operations
                AdminVisaApplicationResource::class,
                PaymentResource::class,
                // Compliance
                AuditLogResource::class,
            ])
            ->widgets([
                KpiOverviewWidget::class,
                ApplicationFunnelWidget::class,
                PaymentMetricsWidget::class,
                OfficerWorkloadWidget::class,
            ])
            ->pages([
                AdminDashboardPage::class,
                ReportsPage::class,
                ExportsPage::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('Identity'),
                NavigationGroup::make('Configuration'),
                NavigationGroup::make('Operations'),
                NavigationGroup::make('Compliance'),
                NavigationGroup::make('Reports'),
            ]);
    }
}
```

### 6.2 Admin resource map

| Resource | Table(s) | Group | C | E | D | Notes |
|---|---|---|---|---|---|---|
| `UserResource` | `users`, roles | Identity | Staff only | ✓ | ✗ Suspend only | — |
| `CountryResource` | `countries` | Configuration | ✓ | ✓ | ✗ | — |
| `VisaTypeResource` | `visa_types` | Configuration | ✓ | ✓ | ✗ Deactivate | — |
| `VisaFeeResource` | `visa_fees` | Configuration | ✓ | ✗ New row only | ✗ | Immutable rows |
| `FormTemplateResource` | `form_templates` | Configuration | Draft only | Draft only | ✗ | Immutable on publish |
| `DocumentTypeResource` | `document_types`, requirements | Configuration | ✓ | ✓ | ✗ | — |
| `ServiceLocationResource` | `service_locations` | Configuration | ✓ | ✓ | ✗ | — |
| `AdminVisaApplicationResource` | `visa_applications` | Operations | ✗ | Override only | ✗ | Full scope, bulk actions |
| `PaymentResource` | `payments`, `invoices` | Operations | ✗ | Refund only | ✗ | Finance + super_admin |
| `AuditLogResource` | `audit_logs` | Compliance | ✗ | ✗ | ✗ | Read-only always |

### 6.3 Admin-only actions

#### `UserResource` actions
```php
// Assign role
Action::make('assign_role')
    ->authorize(fn() => auth()->user()->hasRole('super_admin'))
    ->form([Select::make('role')->options(Role::pluck('name', 'name'))->required()])
    ->action(fn($record, $data) => AssignRole::run($record, $data['role'])),

// Suspend
Action::make('suspend')
    ->authorize(fn($record) => !$record->hasRole('super_admin'))
    ->requiresConfirmation()
    ->action(fn($record) => SuspendUser::run($record)),

// Invite staff
Action::make('invite_staff')
    ->authorize(fn() => auth()->user()->hasRole('super_admin'))
    ->form([
        TextInput::make('email')->email()->required(),
        Select::make('role')
            ->options(['case_officer', 'finance_officer', 'support_staff'])
            ->required(),
    ])
    ->action(fn($data) => InviteStaffMember::run($data['email'], $data['role'])),
    // Sends signed invitation link — expires in 24 hours
```

#### `VisaFeeResource` — immutability
```php
// The Edit action does not exist on this resource
public static function canEdit(Model $record): bool
{
    return false; // Always. Create a new row to change a fee.
}

// Creating a new rule auto-deactivates any overlapping active rule
// via CreateVisaFeeRule Domain Action
```

#### `FormTemplateResource` — publish state machine
```php
// Publish action — irreversible
Action::make('publish')
    ->authorize(fn() => auth()->user()->hasRole('super_admin'))
    ->visible(fn($record) => $record->published_at === null)
    ->requiresConfirmation()
    ->modalDescription('Publishing is irreversible. The schema becomes immutable and
        the previous active version is archived.')
    ->action(fn($record) => PublishFormTemplate::run($record)),

// Create new version from published template
Action::make('create_new_version')
    ->visible(fn($record) => $record->published_at !== null)
    ->action(fn($record) => CreateFormTemplateDraft::run($record)),

// Block editing published templates
public static function canEdit(Model $record): bool
{
    return $record->published_at === null; // Draft only
}
```

#### `AdminVisaApplicationResource` — admin-only actions
```php
// Bulk reassign
BulkAction::make('bulk_reassign')
    ->authorize(fn() => auth()->user()->hasRole('super_admin'))
    ->form([
        Select::make('officer_id')
            ->options(User::role('case_officer')->pluck('name', 'id'))
            ->required()
    ])
    ->action(fn($records, $data) =>
        BulkReassignApplications::run($records, User::find($data['officer_id']))
    ),

// Override status (with mandatory reason + full audit)
Action::make('override_status')
    ->authorize(fn() => auth()->user()->hasRole('super_admin'))
    ->form([
        Select::make('new_status')->options(ApplicationStatus::class)->required(),
        Textarea::make('reason')
            ->required()
            ->minLength(20)
            ->helperText('This reason is written to the audit log'),
    ])
    ->action(fn($record, $data) =>
        AdminOverrideStatus::run($record, $data['new_status'], $data['reason'])
    ),
    // Writes: audit_logs with old_values, new_values, reason, actor, IP

// Queued export
Action::make('export')
    ->authorize(fn() => auth()->user()->hasRole('super_admin'))
    ->form([
        DatePicker::make('date_from')->required(),
        DatePicker::make('date_to')->required(),
        Select::make('format')->options(['csv' => 'CSV', 'xlsx' => 'XLSX']),
        Toggle::make('redact_sensitive')
            ->default(true)
            ->label('Redact passport numbers and dates of birth'),
    ])
    ->action(fn($data) => dispatch(new ExportApplicationsJob($data, auth()->user())))
    // Dispatches to reports queue
    // File written to private storage with 24h expiry
    // Admin receives database notification with download link
    // Writes audit_logs: actor, IP, filters, format, row_count
```

#### `AuditLogResource` — zero mutations
```php
public static function canCreate(): bool      { return false; }
public static function canEdit(Model $r): bool { return false; }
public static function canDelete(Model $r): bool { return false; }
public static function canDeleteAny(): bool   { return false; }
```

### 6.4 Read model tables (admin dashboard only)

Written nightly by `GenerateDailyMetricsJob` on `reports` queue at 02:00.
**Never written to by any Filament resource or HTTP request.**

```sql
-- daily_application_metrics
date  country_id  visa_type_id
submitted_count  approved_count  rejected_count  pending_count
avg_processing_hours  sla_breach_count
PRIMARY KEY (date, country_id, visa_type_id)

-- daily_payment_metrics
date  provider  currency
gross_amount  successful_count  failed_count
refund_amount  reconciliation_variance
PRIMARY KEY (date, provider, currency)

-- officer_performance_metrics
date  officer_id
assigned_count  completed_count
avg_turnaround_hours  rework_count
PRIMARY KEY (date, officer_id)

-- document_rejection_metrics
date  document_type_id  reason_category
rejection_count  resubmission_count
PRIMARY KEY (date, document_type_id, reason_category)
```

### 6.5 Admin dashboard widgets

```php
// KpiOverviewWidget — reads daily_application_metrics
Stat::make('Applications MTD',   $mtd->sum('submitted_count')),
Stat::make('Approval rate',      $pct.'%')->color('success'),
Stat::make('SLA breaches',       $today->sum('sla_breach_count'))->color('danger'),
Stat::make('Avg. processing',    $days.'d'),
Stat::make('Revenue MTD',        $revenue)->color('primary'),
Stat::make('Active officers',    $officerCount),

// PaymentMetricsWidget — reads daily_payment_metrics
// Shows gross collected, failed payments, pending refunds, reconciliation variance
// Reconciliation variance highlighted danger if non-zero

// OfficerWorkloadWidget — reads officer_performance_metrics
// Per-officer: name, queue size, completed, avg turnaround, progress bar
// "Reassign cases" button → AdminVisaApplicationResource with unassigned filter
```

---

## 7. Shared domain actions

Every state change runs through a Domain Action. Actions are `final` classes with a `run()` method.

### Audit log action (called by every mutating action)

```php
// app/Domain/Identity/Actions/CreateAuditLog.php
final class CreateAuditLog
{
    public static function run(
        string $action,
        Model  $auditable,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata  = null,
    ): void {
        AuditLog::create([
            'actor_user_id'  => auth()->id(),
            'action'         => $action,
            'auditable_type' => $auditable::class,
            'auditable_id'   => $auditable->getKey(),
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'metadata'       => $metadata,
            'created_at'     => now(),
        ]);
    }
}
```

### Mandatory audit entries

| Action | Log string | old_values | new_values |
|---|---|---|---|
| `ApproveApplication` | `application.approved` | `{status}` | `{status, decided_at, validity}` |
| `RejectApplication` | `application.rejected` | `{status}` | `{status, reason}` |
| `AdminOverrideStatus` | `application.status_overridden` | `{status}` | `{status, reason}` |
| `AssignApplicationToOfficer` | `application.assigned` | `{officer_id}` | `{officer_id}` |
| `BulkReassignApplications` | `application.bulk_reassigned` | per record | `{officer_id}` |
| `AcceptDocument` | `document.accepted` | `{status}` | `{status, reviewed_by}` |
| `RejectDocument` | `document.rejected` | `{status}` | `{status, reason}` |
| `DocumentDownloaded` | `document.downloaded` | — | `{version_id}` |
| `AssignRole` | `admin.role_assigned` | `{roles}` | `{role}` |
| `SuspendUser` | `user.suspended` | `{status: active}` | `{status: suspended}` |
| `PublishFormTemplate` | `form_template.published` | `{published_at: null}` | `{published_at}` |
| `ExportApplicationsJob` | `export.applications` | — | `{filters, format, rows}` |

---

## 8. Queue configuration

```
high        # Payment webhooks, critical application transitions
default     # General background work
emails      # All outbound notifications — isolate provider failures
documents   # Virus scanning, metadata extraction
pdfs        # Receipt, summary, appointment, decision letter generation
reports     # Exports, daily snapshots, dashboard aggregations
```

**Rule:** No PDF generation or email sending in HTTP requests. Always dispatch a queued job.

```php
// Horizon supervisor config
'queue' => ['high', 'default', 'emails', 'documents', 'pdfs', 'reports'],
```

---

## 9. Security rules

| # | Rule |
|---|---|
| 1 | Every sensitive model has a Policy. No exceptions. |
| 2 | Every custom Filament action calls `->authorize()` explicitly. Filament does not auto-authorize. |
| 3 | Documents stored on private disk only. Signed time-limited URLs for previews. |
| 4 | Never expose raw database IDs in public URLs or emails. Use tracking numbers or ULIDs. |
| 5 | Server-side document validation: MIME type, extension, size, page/image limits. |
| 6 | Store original filenames as metadata only. Use ULID-based object paths. |
| 7 | All workflow state transitions wrapped in `DB::transaction()`. |
| 8 | Webhook handling is idempotent — `unique(provider, provider_event_id)`. |
| 9 | Decision letters generated from immutable `application_snapshots` only. |
| 10 | Block document preview/download until `virus_scan_status = clean`. |
| 11 | Rate-limit: login 5/min, tracking 10/hr, upload, OTP, webhook retry. |
| 12 | MFA required for `case_officer`, `senior_officer`, `finance_officer`, `admin`, `super_admin`. |
| 13 | Officer panel scope enforced in Policy, not a removable UI filter. |
| 14 | Internal review notes never returned by applicant-facing queries — policy enforced. |
| 15 | Every document download writes to `audit_logs` — no exceptions. |

---

## 10. Testing strategy

### Minimum test coverage per milestone

| Type | What to cover |
|---|---|
| Unit | Fee calculation, tracking number generation, status transitions |
| Feature | Full HTTP flows: submit, upload, pay, officer review |
| Policy | Every model policy — applicant, officer, finance, admin, cross-user isolation |
| Webhook | Signature rejection, duplicate events, out-of-order events, failed payment retries |
| Queue | Jobs dispatched correctly and retryable |
| Browser | Critical applicant + officer flows (Laravel Dusk, M7) |

### Critical policy tests (must pass before merge)

```
- Applicant cannot view another applicant's application or documents
- Applicant cannot edit a submitted application unless status = info_requested
- Officer cannot approve an unassigned application (unless senior_officer policy)
- Application cannot be approved while required docs are missing/infected/rejected
- Officer cannot see another officer's assigned queue (case_officer role)
- Admin override records reason, actor, IP, old_values, new_values in audit_logs
- Document download by officer writes audit_logs entry
- AuditLogResource has zero create/edit/delete capabilities
- VisaFeeResource has zero edit capability
- FormTemplateResource blocks edit when published_at is set
```

---

## 11. Implementation checklist

### Phase 0 — Skeleton (Milestone 0)
- [ ] Laravel 12 project with domain scaffold
- [ ] `AdminPanelProvider` at `/admin` with `super_admin` gate + MFA middleware
- [ ] `OfficerPanelProvider` at `/officer` with role gate + MFA middleware
- [ ] Horizon configured with 6 named queues
- [ ] Pest + Pint installed and passing
- [ ] `CLAUDE.md` at project root
- [ ] `php artisan test` passes · `php artisan pint --test` passes

### Phase 1 — Identity & config (Milestone 1)
- [ ] All migrations from section 4 through `visa_fees`
- [ ] `RolesAndPermissionsSeeder` creates all 9 canonical roles
- [ ] `SuperAdminSeeder` reads from `.env` — no hardcoded credentials
- [ ] `UserResource`, `CountryResource`, `VisaTypeResource`, `VisaFeeResource` in admin panel
- [ ] Every model has a Policy with tests
- [ ] Cross-user isolation confirmed

### Phase 2 — Application workflow (Milestone 2)
- [ ] `form_templates`, `visa_applications`, `application_answers`,
      `application_status_histories`, `application_snapshots` migrations
- [ ] `CreateDraftApplication`, `UpdateApplicationSection`, `SubmitApplication`,
      `GenerateTrackingNumber` actions
- [ ] Snapshot immutability confirmed in tests
- [ ] Status history append-only confirmed in tests

### Phase 3 — Documents (Milestone 3)
- [ ] Document tables migrated
- [ ] Private disk only — public disk test asserts failure
- [ ] ULID paths, SHA-256 checksum, filename as metadata
- [ ] `ScanDocumentJob` dispatched to `documents` queue
- [ ] Every document action writes `audit_logs`
- [ ] `ApplicationDocumentPolicy` tests passing

### Phase 4 — Payments (Milestone 4)
- [ ] Payment tables migrated
- [ ] Webhook idempotency test: duplicate event does not duplicate records
- [ ] Receipt PDF uses frozen invoice data
- [ ] `finance_officer` cannot alter applications

### Phase 5 — Officer panel (Milestone 5)
- [ ] `OfficerVisaApplicationResource` with all actions and relation managers
- [ ] Officer scope enforced by Policy (not removable filter)
- [ ] Document lightbox: preview logs to `audit_logs`
- [ ] All 5 decision actions have `->authorize()`
- [ ] `review_notes.visibility = internal` excluded from applicant queries
- [ ] `AppointmentResource` created
- [ ] MFA enforced — `/officer` inaccessible without MFA

### Phase 6 — Notifications & PDFs (Milestone 6)
- [ ] All lifecycle events dispatch queued notification jobs
- [ ] All PDF jobs dispatch to `pdfs` queue
- [ ] PDF Blade templates receive DTOs only — zero DB queries inside
- [ ] Failed jobs visible and retryable in Horizon

### Phase 7 — Admin panel + reporting (Milestone 7)
- [ ] All 10 admin resources registered and tested
- [ ] `KpiOverviewWidget` confirmed reading from read models only (query logger)
- [ ] `AuditLogResource` confirms zero create/edit/delete
- [ ] `VisaFeeResource` confirms edit is impossible
- [ ] `FormTemplateResource` publish locks schema — confirmed in test
- [ ] `GenerateDailyMetricsJob` populates all 4 read model tables
- [ ] Queued exports write to private storage, notify admin, log to `audit_logs`
- [ ] Rate limits configured for all sensitive endpoints
- [ ] MFA enforced for all staff roles
- [ ] `php artisan test` — all tests green
- [ ] `php artisan pint --test` — zero violations
- [ ] Production checklist complete (`APP_DEBUG=false`, HTTPS, backups)

---

## Key references

| Topic | URL |
|---|---|
| Laravel 12 docs | https://laravel.com/docs/12.x |
| Filament 4 docs | https://filamentphp.com/docs |
| Filament 4 actions | https://filamentphp.com/docs/4.x/actions |
| Filament 4 resources | https://filamentphp.com/docs/4.x/resources |
| Spatie Permission | https://spatie.be/docs/laravel-permission |
| Spatie Activitylog | https://spatie.be/docs/laravel-activitylog |
| Spatie Laravel Data | https://spatie.be/docs/laravel-data |
| Laravel Horizon | https://laravel.com/docs/12.x/horizon |
| Laravel Policies | https://laravel.com/docs/12.x/authorization |
| Stripe webhooks | https://docs.stripe.com/webhooks |
| Stripe Checkout | https://docs.stripe.com/api/checkout/sessions |
| Dompdf package | https://github.com/barryvdh/laravel-dompdf |
| PostgreSQL JSONB | https://www.postgresql.org/docs/current/datatype-json.html |
