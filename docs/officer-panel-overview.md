# Officer Panel — Project Overview & Claude Code Prompt
# Visa Application System · Laravel 12 · Filament 4
# ─────────────────────────────────────────────────────────────────────────────
# USAGE: Paste the section marked "CLAUDE CODE PROMPT" directly into Claude Code.
# Keep this full document in docs/officer-panel-overview.md in your repo.
# ─────────────────────────────────────────────────────────────────────────────


# ════════════════════════════════════════════════════════════════════════════
# PART 1 — PROJECT OVERVIEW  (for humans, architects, and stakeholders)
# ════════════════════════════════════════════════════════════════════════════

## What the officer panel is

The officer panel is a Filament 4 panel mounted at `/officer`. It is the
primary workspace for case officers, senior officers, and document verifiers.
Officers use it to:

  - Work their assigned application queue
  - Review and verify uploaded documents
  - Add internal notes
  - Request additional information from applicants
  - Schedule appointments (biometrics / interview)
  - Approve or reject applications
  - View status history and audit trails

The panel does NOT handle payments, user registration, public tracking,
admin configuration, or reporting. Those belong to the admin panel or the
applicant portal.

---

## Who uses it

| Role              | What they can do                                                   |
|-------------------|--------------------------------------------------------------------|
| case_officer      | See assigned applications only. Review, note, request info, decide |
| senior_officer    | See full team queue. Reassign. Override. All case_officer actions  |
| document_verifier | Accept / reject documents on assigned applications only            |
| finance_officer   | View payment status on any application. Cannot alter workflow      |

Role enforcement is at the Policy level — NOT just the UI. Every Filament
action calls ->authorize() explicitly.

---

## Application lifecycle visible to officers

Officers see applications from the point they are assigned onward:

  submitted → payment_pending → paid → [ASSIGNED] under_review
    → document_verification
    → info_requested          (officer sends correction request)
    → resubmitted             (applicant responds)
    → interview_scheduled     (officer schedules appointment)
    → decision_pending
    → approved | rejected
    → closed

Officers cannot see draft applications or applications assigned to others
(unless senior_officer role widens the scope via policy).

---

## The seven panel pages

### 1. My queue  (/officer/visa-applications)
The main landing page. Shows applications assigned to auth()->id().
Sorted by SLA urgency (breached first, then by days remaining).
Left-border colour coding: red = breached, amber = ≤2 days, purple = new.
Stats row reads from officer_performance_metrics (read model, not live COUNT).

### 2. Team queue  (/officer/team-queue)
Senior officer only. All applications across the team in under_review,
document_verification, or resubmitted. Includes unassigned and SLA-breached.
Bulk reassign action available.

### 3. SLA breaches  (/officer/sla-breaches)
Applications where decided_at is null AND submitted_at + processing_days_max
< now(). Named Eloquent scope: VisaApplication::slaBreached().
Triggers supervisor alert notification.

### 4. Application detail  (/officer/visa-applications/{id})
The core review workspace. Tabbed form answers (personal, travel, employment).
Document list with per-document accept/reject/view actions.
Internal notes panel (visibility toggle: internal vs applicant-visible).
Status history timeline (append-only, read-only in UI).
Action bar: Schedule appointment | Request info | Reject | Approve.

### 5. Appointments  (/officer/appointments)
Calendar view of interview and biometrics appointments.
Create, reschedule, mark completed or missed.
Drives appointment letter PDF generation.

### 6. Search all  (/officer/search)
Senior officer and above. Full-text search across all applications.
Searches: applicant name, tracking_number, passport (hash indexed).

### 7. My performance  (/officer/performance)
Per-officer metrics from officer_performance_metrics read model.
Assigned count, completed count, avg turnaround, rework count.
Officer sees own row only.

---

## Key domain actions used by this panel

All business logic lives in app/Domain/. Filament resources call these
actions — they do not contain business logic themselves.

| Action                        | Triggered by                          |
|-------------------------------|---------------------------------------|
| AssignApplicationToOfficer    | Queue table bulk action / auto-assign |
| AcceptDocument                | Document row action in detail view    |
| RejectDocument                | Document row action with reason       |
| RequestAdditionalInformation  | Action modal — unlocks fields + docs  |
| ScheduleAppointment           | Appointment modal                     |
| ApproveApplication            | Approve modal with validity options   |
| RejectApplication             | Reject modal with reason enum         |
| AddReviewNote                 | Notes panel add button                |

---

## Authorization matrix (enforced in Policies)

| Action                    | case_officer    | senior_officer  | document_verifier |
|---------------------------|-----------------|-----------------|-------------------|
| View assigned application | ✓ own only      | ✓ all           | ✓ own only        |
| View team queue           | ✗               | ✓               | ✗                 |
| Accept document           | ✓ assigned only | ✓ all           | ✓ assigned only   |
| Reject document           | ✓ assigned only | ✓ all           | ✓ assigned only   |
| Request info              | ✓ assigned only | ✓ all           | ✗                 |
| Approve application       | ✓ assigned only | ✓ all           | ✗                 |
| Reject application        | ✓ assigned only | ✓ all           | ✗                 |
| Reassign application      | ✗               | ✓               | ✗                 |
| Schedule appointment      | ✓ assigned only | ✓ all           | ✗                 |
| Add internal note         | ✓ assigned only | ✓ all           | ✓ assigned only   |
| View audit log            | ✗               | limited         | ✗                 |
| View payment detail       | read-only       | read-only       | ✗                 |

Approve is disabled (->disabled() closure) when any required document
is not in `accepted` state, or when status is not under_review /
document_verification / resubmitted.

---

## Critical guard rails

1. Officers CANNOT approve if any required document is missing, scanning,
   infected, or rejected. Enforced in ApproveApplication action, not just UI.

2. Identity fields (name, DOB, passport number) are NEVER unlockable in
   RequestAdditionalInformation, even by senior officers. Hardcoded in action.

3. All decisions record: decided_by_user_id, decided_at, from_status,
   to_status, reason. Written inside DB::transaction.

4. Documents served only via temporary signed S3 URLs (5-minute expiry)
   after ApplicationDocumentPolicy::view() passes.

5. Every document download writes an audit_log row.

6. review_notes with visibility = 'internal' are NEVER returned by any
   applicant-facing API. Enforced at query scope level, not just controller.

---

## File map — every file to create

```
app/
  Filament/
    Officer/
      OfficerPanelProvider.php          # Panel registration
      Resources/
        VisaApplicationResource.php     # Main queue resource
        VisaApplicationResource/
          Pages/
            ListVisaApplications.php    # My queue page
            ViewVisaApplication.php     # Application detail page
          RelationManagers/
            DocumentsRelationManager.php
            ReviewNotesRelationManager.php
            StatusHistoriesRelationManager.php
            PaymentsRelationManager.php
      Pages/
        TeamQueue.php                   # Senior officer page
        SlaBreaches.php                 # Breached SLA page
        OfficerPerformance.php          # My performance page
        Appointments.php                # Appointments calendar
      Widgets/
        OfficerStatsWidget.php          # Queue stats row
        SlaAlertWidget.php              # Breach alert banner

  Domain/
    Applications/
      Actions/
        AssignApplicationToOfficer.php
        ApproveApplication.php
        RejectApplication.php
        RequestAdditionalInformation.php
      Enums/
        ApplicationStatus.php           # (already exists from M2)
      Policies/
        VisaApplicationPolicy.php       # (extend with officer methods)
    Documents/
      Actions/
        AcceptDocument.php
        RejectDocument.php
      Policies/
        ApplicationDocumentPolicy.php
    Applications/
      Actions/
        AddReviewNote.php
        ScheduleAppointment.php

database/
  migrations/
    xxxx_create_review_notes_table.php  # (if not already in M3)
    xxxx_create_appointments_table.php
    xxxx_create_officer_performance_metrics_table.php

tests/
  Feature/
    Officer/
      OfficerQueueTest.php
      ApplicationDetailTest.php
      DocumentReviewTest.php
      DecisionActionsTest.php
  Unit/
    Policies/
      VisaApplicationPolicyTest.php
      ApplicationDocumentPolicyTest.php
```

---

## SLA calculation

SLA remaining days is computed as a named scope, not in PHP, so it sorts:

```php
// app/Domain/Applications/Models/VisaApplication.php
public function scopeSlaBreached(Builder $q): Builder
{
    return $q->whereNull('decided_at')
             ->whereNotNull('submitted_at')
             ->join('visa_types', 'visa_applications.visa_type_id', '=', 'visa_types.id')
             ->whereRaw('submitted_at + (visa_types.processing_days_max || \' days\')::interval < NOW()');
}

public function getSlaRemainingDaysAttribute(): int
{
    if ($this->decided_at) return 0;
    $deadline = $this->submitted_at->addDays($this->visaType->processing_days_max);
    return (int) now()->diffInDays($deadline, false);
}
```

---

## Read model for stats

OfficerStatsWidget reads from officer_performance_metrics, NOT live queries:

```
officer_performance_metrics:
  date            date
  officer_id      ulid FK users
  assigned_count  integer
  completed_count integer
  avg_turnaround_hours float
  rework_count    integer
```

Populated nightly by GenerateDailyMetricsJob on the reports queue.
Widget queries: WHERE officer_id = auth()->id() AND date >= now()-30days.


# ════════════════════════════════════════════════════════════════════════════
# PART 2 — CLAUDE CODE PROMPT  (paste this into Claude Code directly)
# ════════════════════════════════════════════════════════════════════════════

---

Implement **Milestone 5 — Officer Panel** for the Visa Application System.

## Context

- Laravel 12 · PHP 8.3+ · Filament 4 · PostgreSQL · Redis
- CLAUDE.md is in the project root — read it before writing any code
- Domain folder: app/Domain/ — ALL business logic lives here
- Filament resources are thin: they call Domain Actions, never Eloquent directly
- ULIDs for all sensitive primary keys
- Pest for tests · Pint for code style
- Milestones 0–4 are complete: users, roles, visa_types, visa_fees, form_templates,
  visa_applications, application_answers, application_snapshots, application_status_histories,
  document_types, visa_type_document_requirements, application_documents, document_versions,
  payments, payment_items, payment_webhook_events, invoices all exist with migrations and models
- Roles already seeded: applicant, agent, case_officer, senior_officer,
  document_verifier, finance_officer, support_staff, admin, super_admin

## What to build

### Step 1 — OfficerPanelProvider

Create `app/Filament/Officer/OfficerPanelProvider.php`:

```php
<?php

namespace App\Filament\Officer;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

class OfficerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('officer')
            ->path('officer')
            ->colors(['primary' => Color::Sky])
            ->login()
            ->authMiddleware(['auth', 'verified'])
            ->middleware([
                \App\Http\Middleware\EnsureOfficerRole::class,
            ])
            ->discoverResources(in: app_path('Filament/Officer/Resources'), for: 'App\\Filament\\Officer\\Resources')
            ->discoverPages(in: app_path('Filament/Officer/Pages'), for: 'App\\Filament\\Officer\\Pages')
            ->discoverWidgets(in: app_path('Filament/Officer/Widgets'), for: 'App\\Filament\\Officer\\Widgets');
    }
}
```

Create `app/Http/Middleware/EnsureOfficerRole.php` that redirects to /login
if the authenticated user does not have one of: case_officer, senior_officer,
document_verifier, finance_officer, admin, super_admin.

Register both in `bootstrap/providers.php`.

---

### Step 2 — Missing migrations

Create migrations for any tables not yet present. Check existing migrations first.

**review_notes** (if not created in M3):
```
id              ulid PK
application_id  ulid FK visa_applications restrictOnDelete
author_user_id  ulid FK users nullOnDelete
visibility      enum ['internal', 'applicant']
note            text
timestamps      (created_at, updated_at)
```

**appointments**:
```
id                ulid PK
application_id    ulid FK visa_applications restrictOnDelete
location_id       ulid FK service_locations nullOnDelete
type              enum ['biometrics', 'interview', 'document_drop']
scheduled_at      timestamp
status            enum ['scheduled', 'completed', 'missed', 'cancelled']
notes             text nullable
timestamps
```

**service_locations**:
```
id        ulid PK
name      string
city      string
country   string
is_active boolean default true
timestamps
```

**officer_performance_metrics**:
```
id                    ulid PK
date                  date
officer_id            ulid FK users nullOnDelete
assigned_count        integer default 0
completed_count       integer default 0
avg_turnaround_hours  float nullable
rework_count          integer default 0
unique(date, officer_id)
```

---

### Step 3 — Domain Actions

Create each action as a `final` class with a static `run()` method.
Wrap all state transitions in `DB::transaction()`.

#### AssignApplicationToOfficer

File: `app/Domain/Applications/Actions/AssignApplicationToOfficer.php`

```php
<?php

namespace App\Domain\Applications\Actions;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class AssignApplicationToOfficer
{
    public static function run(VisaApplication $application, User $officer): VisaApplication
    {
        return DB::transaction(function () use ($application, $officer) {
            $application->update([
                'assigned_to_user_id' => $officer->id,
                'status' => ApplicationStatus::UnderReview,
            ]);

            $application->statusHistories()->create([
                'from_status'   => $application->getOriginal('status'),
                'to_status'     => ApplicationStatus::UnderReview->value,
                'actor_user_id' => auth()->id(),
                'reason'        => null,
                'metadata'      => ['assigned_to' => $officer->id],
            ]);

            activity()
                ->performedOn($application)
                ->causedBy(auth()->user())
                ->withProperties(['assigned_to' => $officer->id])
                ->log('application.assigned');

            return $application->fresh();
        });
    }
}
```

#### AcceptDocument

File: `app/Domain/Documents/Actions/AcceptDocument.php`

- Sets `application_documents.status = 'accepted'`
- Sets `reviewed_by_user_id = auth()->id()` and `reviewed_at = now()`
- Writes audit_log row: `document.accepted`
- Wrapped in DB::transaction

#### RejectDocument

File: `app/Domain/Documents/Actions/RejectDocument.php`

- Sets `application_documents.status = 'rejected'`
- Sets `rejection_reason` from the enum value passed in
- Sets `reviewed_by_user_id` and `reviewed_at`
- Checks if all required documents are now blocked → if so, transitions
  application to `info_requested` and calls RequestAdditionalInformation
  with `unlocked_fields = []` and only the rejected document flagged
- Writes audit_log row: `document.rejected`
- Notifies applicant (dispatch NotifyApplicantDocumentRejected on emails queue)

#### RequestAdditionalInformation

File: `app/Domain/Applications/Actions/RequestAdditionalInformation.php`

Parameters:
- VisaApplication $application
- string $messageToApplicant
- array $documentsToResubmit  (array of application_document IDs)
- array $fieldsToUnlock        (array of 'section_key.field_key' strings)
- int $deadlineDays = 7

Steps (all in DB::transaction):
1. Validate: identity fields (first_name, last_name, date_of_birth,
   nationality, passport_number) MUST NOT appear in $fieldsToUnlock.
   Throw \InvalidArgumentException if they do.
2. Reset status of each document in $documentsToResubmit to 'pending'
3. Create review_notes row: visibility = 'applicant', note = $messageToApplicant,
   metadata includes unlocked_fields and deadline
4. Transition application status: any → info_requested
5. Write application_status_histories row
6. Write audit_log row: application.info_requested
7. Dispatch NotifyApplicantInfoRequested on emails queue

#### ApproveApplication

File: `app/Domain/Applications/Actions/ApproveApplication.php`

Parameters:
- VisaApplication $application
- string $validityPeriod = '12_months'
- string $entryType = 'single'
- string|null $internalNotes = null

Guard checks (throw \DomainException if violated):
- Status must be under_review, document_verification, or resubmitted
- Application must be assigned to auth()->id() OR actor has senior_officer role
- All required documents (from visa_type_document_requirements) must exist
  in application_documents with status = 'accepted'
- No document may have virus_scan_status = 'infected' or 'pending'

Steps (all in DB::transaction):
1. Calculate valid_until based on validityPeriod + decided_at
2. Update visa_applications: status = approved, decision = 'approved',
   decided_by_user_id = auth()->id(), decided_at = now(),
   metadata merged with {valid_until, validity_period, entry_type}
3. Write application_status_histories row
4. Write internal review_note if $internalNotes provided
5. Write audit_log row: application.approved with old/new values
6. Fire ApplicationApproved event → listeners:
   - GenerateDecisionLetterPdfJob (pdfs queue)
   - NotifyApplicantApproved (emails queue)
   - NotifyFinanceTeam (emails queue)

#### RejectApplication

File: `app/Domain/Applications/Actions/RejectApplication.php`

Parameters:
- VisaApplication $application
- string $rejectionReason  (from RejectionReason enum)
- string $explanationForApplicant
- string|null $internalNotes = null

Guard checks:
- Status must be under_review, document_verification, or resubmitted
- Application must be assigned to auth()->id() OR senior_officer role
- $rejectionReason must be a valid RejectionReason enum value

Steps (all in DB::transaction):
1. Update visa_applications: status = rejected, decision = 'rejected',
   decided_by_user_id, decided_at, metadata with reason and explanation
2. Write application_status_histories row
3. Write applicant-visible review_note containing explanation
4. Write audit_log row: application.rejected with reason
5. Fire ApplicationRejected event → listeners:
   - NotifyApplicantRejected (emails queue)

#### ScheduleAppointment

File: `app/Domain/Applications/Actions/ScheduleAppointment.php`

Parameters:
- VisaApplication $application
- string $type  ('biometrics' | 'interview' | 'document_drop')
- string $locationId
- Carbon $scheduledAt
- string|null $notes

Steps (all in DB::transaction):
1. Create appointments row
2. Transition application to interview_scheduled
3. Write status history row
4. Write audit_log row: application.appointment_scheduled
5. Dispatch GenerateAppointmentLetterPdfJob (pdfs queue)
6. Dispatch NotifyApplicantAppointmentScheduled (emails queue)

#### AddReviewNote

File: `app/Domain/Applications/Actions/AddReviewNote.php`

- Creates review_notes row with visibility (internal | applicant)
- Writes audit_log: note.added
- If visibility = applicant, dispatches database notification to applicant

---

### Step 4 — Enums

Create `app/Domain/Applications/Enums/RejectionReason.php`:

```php
enum RejectionReason: string
{
    case InsufficientFinancialEvidence  = 'insufficient_financial_evidence';
    case InvalidOrExpiredDocument       = 'invalid_or_expired_document';
    case IncompleteApplication          = 'incomplete_application';
    case PreviousViolation              = 'previous_visa_violation';
    case IneligibleNationality          = 'ineligible_nationality';
    case DoesNotMeetCriteria            = 'does_not_meet_eligibility_criteria';
    case Other                          = 'other';

    public function label(): string
    {
        return match($this) {
            self::InsufficientFinancialEvidence => 'Insufficient financial evidence',
            self::InvalidOrExpiredDocument      => 'Invalid or expired travel document',
            self::IncompleteApplication         => 'Incomplete application',
            self::PreviousViolation             => 'Previous visa violation',
            self::IneligibleNationality         => 'Ineligible nationality',
            self::DoesNotMeetCriteria           => 'Does not meet eligibility criteria',
            self::Other                         => 'Other',
        };
    }
}
```

---

### Step 5 — Policies

Extend `app/Domain/Applications/Policies/VisaApplicationPolicy.php`
with officer-specific methods:

```php
public function viewAssigned(User $user, VisaApplication $application): bool
{
    if ($user->hasRole(['senior_officer', 'admin', 'super_admin'])) return true;
    return $application->assigned_to_user_id === $user->id;
}

public function approve(User $user, VisaApplication $application): bool
{
    if (!$user->hasRole(['case_officer', 'senior_officer', 'admin', 'super_admin'])) return false;
    if ($user->hasRole('case_officer') && $application->assigned_to_user_id !== $user->id) return false;
    return in_array($application->status, [
        ApplicationStatus::UnderReview->value,
        ApplicationStatus::DocumentVerification->value,
        ApplicationStatus::Resubmitted->value,
    ]);
}

public function reject(User $user, VisaApplication $application): bool
{
    return $this->approve($user, $application); // same guards
}

public function requestInfo(User $user, VisaApplication $application): bool
{
    if (!$user->hasRole(['case_officer', 'senior_officer', 'admin', 'super_admin'])) return false;
    if ($user->hasRole('case_officer') && $application->assigned_to_user_id !== $user->id) return false;
    return true;
}

public function reassign(User $user, VisaApplication $application): bool
{
    return $user->hasRole(['senior_officer', 'admin', 'super_admin']);
}
```

Create `app/Domain/Documents/Policies/ApplicationDocumentPolicy.php`:

```php
public function view(User $user, ApplicationDocument $document): bool
{
    // Finance can view payment-related, not documents
    if ($user->hasRole('finance_officer')) return false;
    if ($user->hasRole(['admin', 'super_admin', 'senior_officer'])) return true;
    return $document->application->assigned_to_user_id === $user->id;
}

public function accept(User $user, ApplicationDocument $document): bool
{
    if (!$user->hasRole(['case_officer', 'senior_officer', 'document_verifier', 'admin', 'super_admin'])) return false;
    if ($user->hasRole(['case_officer', 'document_verifier'])) {
        return $document->application->assigned_to_user_id === $user->id;
    }
    return true;
}

public function reject(User $user, ApplicationDocument $document): bool
{
    return $this->accept($user, $document);
}
```

---

### Step 6 — VisaApplicationResource (Officer panel)

File: `app/Filament/Officer/Resources/VisaApplicationResource.php`

#### Table columns

```php
Tables\Columns\TextColumn::make('applicantProfile.full_name')
    ->label('Applicant')
    ->description(fn($r) => $r->tracking_number)
    ->searchable(['applicant_profiles.first_name', 'applicant_profiles.last_name'])
    ->sortable(),

Tables\Columns\TextColumn::make('visaType.name')
    ->label('Type')
    ->badge()
    ->color('info'),

Tables\Columns\TextColumn::make('status')
    ->label('Status')
    ->badge()
    ->color(fn($state) => match($state) {
        'under_review'           => 'purple',
        'document_verification'  => 'warning',
        'resubmitted'            => 'warning',
        'info_requested'         => 'danger',
        'approved'               => 'success',
        'rejected'               => 'danger',
        default                  => 'gray',
    }),

Tables\Columns\TextColumn::make('sla_remaining_days')
    ->label('SLA')
    ->getStateUsing(fn($r) => $r->sla_remaining_days)
    ->badge()
    ->color(fn($state) => match(true) {
        $state < 0  => 'danger',
        $state <= 2 => 'warning',
        default     => 'gray',
    })
    ->formatStateUsing(fn($state) => $state < 0 ? 'Breached' : "{$state}d left"),
```

#### Record classes (left-border colour coding)

```php
->recordClasses(fn($record) => match(true) {
    $record->sla_remaining_days < 0 => 'border-l-4 border-red-500',
    $record->sla_remaining_days <= 2 => 'border-l-4 border-amber-500',
    $record->submitted_at?->isAfter(now()->subDay()) => 'border-l-4 border-purple-500',
    default => '',
})
```

#### Default query scope (assigned to me)

```php
->modifyQueryUsing(function (Builder $query) {
    $user = auth()->user();
    if ($user->hasRole(['senior_officer', 'admin', 'super_admin'])) return $query;
    return $query->where('assigned_to_user_id', $user->id);
})
```

#### Table filters

- SelectFilter on status using ApplicationStatus enum
- SelectFilter on visa_type_id
- Filter 'sla_at_risk': applies scopeSlaAtRisk (0–2 days remaining)
- Filter 'sla_breached': applies scopeSlaBreached
- Filter 'resubmitted': status = resubmitted
- Filter 'unassigned': assigned_to_user_id IS NULL (senior_officer only, guarded)

#### Table actions (row level)

All actions call ->authorize() before execution.

**AssignAction** (senior_officer only):
```php
Tables\Actions\Action::make('assign')
    ->label('Assign')
    ->icon('heroicon-o-user-plus')
    ->authorize(fn($record) => auth()->user()->can('reassign', $record))
    ->form([
        Forms\Components\Select::make('officer_id')
            ->label('Assign to officer')
            ->options(User::role(['case_officer', 'senior_officer'])->pluck('name', 'id'))
            ->required(),
    ])
    ->action(fn($record, $data) => AssignApplicationToOfficer::run(
        $record, User::findOrFail($data['officer_id'])
    ));
```

**ReviewAction** (primary CTA):
```php
Tables\Actions\Action::make('review')
    ->label('Review')
    ->url(fn($record) => VisaApplicationResource::getUrl('view', ['record' => $record]))
    ->authorize(fn($record) => auth()->user()->can('viewAssigned', $record));
```

#### Bulk actions (senior_officer only)

BulkAction::make('bulk_assign') — reassign multiple selected records.
Guarded: auth()->user()->hasRole(['senior_officer', 'admin', 'super_admin']).

---

### Step 7 — Application detail page (ViewVisaApplication)

File: `app/Filament/Officer/Resources/VisaApplicationResource/Pages/ViewVisaApplication.php`

#### Header actions (action bar)

Rendered as `getHeaderActions()` in the page class:

```
1. ScheduleAppointmentAction
2. RequestInfoAction
3. RejectAction
4. ApproveAction
```

**ApproveAction**:
```php
Actions\Action::make('approve')
    ->label('Approve')
    ->color('success')
    ->icon('heroicon-o-check')
    ->authorize(fn() => auth()->user()->can('approve', $this->record))
    ->disabled(fn() => $this->record->applicationDocuments()
        ->whereIn('document_type_id', /* required type IDs */ [])
        ->where('status', '!=', 'accepted')
        ->exists()
    )
    ->tooltip(fn() => /* "N documents pending" if disabled */ '')
    ->form([
        Forms\Components\Select::make('validity_period')
            ->options(['12_months' => '12 months', '6_months' => '6 months', '3_months' => '3 months'])
            ->default('12_months')->required(),
        Forms\Components\Select::make('entry_type')
            ->options(['single' => 'Single entry', 'multiple' => 'Multiple entry'])
            ->default('single')->required(),
        Forms\Components\Textarea::make('internal_notes')
            ->label('Internal notes (optional — not shown to applicant)'),
    ])
    ->requiresConfirmation()
    ->modalHeading('Confirm approval')
    ->modalDescription(fn() => "Approving {$this->record->tracking_number}. This will generate a decision letter and notify the applicant.")
    ->action(function (array $data) {
        ApproveApplication::run(
            $this->record,
            $data['validity_period'],
            $data['entry_type'],
            $data['internal_notes'] ?? null,
        );
        Notification::make()->title('Application approved')->success()->send();
        $this->redirect(VisaApplicationResource::getUrl('index'));
    });
```

**RejectAction**:
```php
Actions\Action::make('reject')
    ->label('Reject')
    ->color('danger')
    ->icon('heroicon-o-x-mark')
    ->authorize(fn() => auth()->user()->can('reject', $this->record))
    ->form([
        Forms\Components\Select::make('rejection_reason')
            ->options(RejectionReason::class)
            ->required(),
        Forms\Components\Textarea::make('explanation_for_applicant')
            ->label('Explanation (sent to applicant)')
            ->required()
            ->helperText('Be clear but do not disclose internal assessment criteria.'),
        Forms\Components\Textarea::make('internal_notes')
            ->label('Internal notes (not shared with applicant)'),
    ])
    ->requiresConfirmation()
    ->action(function (array $data) {
        RejectApplication::run(
            $this->record,
            $data['rejection_reason'],
            $data['explanation_for_applicant'],
            $data['internal_notes'] ?? null,
        );
        Notification::make()->title('Application rejected')->warning()->send();
        $this->redirect(VisaApplicationResource::getUrl('index'));
    });
```

**RequestInfoAction**:
```php
Actions\Action::make('request_info')
    ->label('Request info')
    ->color('warning')
    ->icon('heroicon-o-question-mark-circle')
    ->authorize(fn() => auth()->user()->can('requestInfo', $this->record))
    ->form([
        Forms\Components\Textarea::make('message')
            ->label('Message to applicant')
            ->required(),
        Forms\Components\CheckboxList::make('documents_to_resubmit')
            ->label('Documents to resubmit')
            ->options(fn() => $this->record->applicationDocuments()
                ->with('documentType')
                ->get()
                ->pluck('documentType.name', 'id')
            ),
        Forms\Components\CheckboxList::make('fields_to_unlock')
            ->label('Application fields to unlock for editing')
            ->options([
                'employment.employer_name' => 'Employer name',
                'employment.job_title'     => 'Job title',
                'travel.purpose'           => 'Purpose of visit',
                'travel.accommodation'     => 'Accommodation address',
                // identity fields intentionally excluded
            ]),
        Forms\Components\Select::make('deadline_days')
            ->options([3 => '3 days (urgent)', 7 => '7 days (default)', 14 => '14 days'])
            ->default(7)->required(),
    ])
    ->action(function (array $data) {
        RequestAdditionalInformation::run(
            $this->record,
            $data['message'],
            $data['documents_to_resubmit'] ?? [],
            $data['fields_to_unlock'] ?? [],
            $data['deadline_days'],
        );
        Notification::make()->title('Information request sent')->success()->send();
    });
```

**ScheduleAppointmentAction**:
```php
Actions\Action::make('schedule_appointment')
    ->label('Schedule appointment')
    ->icon('heroicon-o-calendar')
    ->authorize(fn() => auth()->user()->can('requestInfo', $this->record))
    ->form([
        Forms\Components\Select::make('type')
            ->options(['biometrics' => 'Biometrics', 'interview' => 'Interview', 'document_drop' => 'Document drop-off'])
            ->required(),
        Forms\Components\Select::make('location_id')
            ->options(ServiceLocation::active()->pluck('name', 'id'))
            ->required(),
        Forms\Components\DateTimePicker::make('scheduled_at')->required(),
        Forms\Components\Textarea::make('notes')->label('Notes for applicant'),
    ])
    ->action(function (array $data) {
        ScheduleAppointment::run(
            $this->record,
            $data['type'],
            $data['location_id'],
            Carbon::parse($data['scheduled_at']),
            $data['notes'] ?? null,
        );
        Notification::make()->title('Appointment scheduled')->success()->send();
    });
```

---

### Step 8 — Relation managers

#### DocumentsRelationManager

File: `app/Filament/Officer/Resources/VisaApplicationResource/RelationManagers/DocumentsRelationManager.php`

Shows: document type name, version number, file size, scan status, review status.
Actions per row:
- ViewAction: generates temporary signed URL via Storage::disk('private')->temporaryUrl()
  after ApplicationDocumentPolicy::view() check. Logs audit_log: document.downloaded
- AcceptAction: calls AcceptDocument::run($document). Authorize with policy.
- RejectAction: form with rejection_reason textarea. Calls RejectDocument::run().

No create or delete actions — documents are managed by the applicant only.

#### ReviewNotesRelationManager

File: `app/Filament/Officer/Resources/VisaApplicationResource/RelationManagers/ReviewNotesRelationManager.php`

Shows notes with visibility badge (internal / applicant).
Query scope: ALWAYS exclude visibility = 'applicant' notes from the officer view
IF the reader only has document_verifier role (document verifiers see internal notes
on their assigned cases but not applicant-visible notes to avoid confusion).

Create action:
- Textarea: note
- Radio: visibility (internal | applicant-visible)
- Calls AddReviewNote::run()

No edit or delete (review notes are append-only by convention).

#### StatusHistoriesRelationManager

Read-only. Shows: from_status → to_status, actor, timestamp, reason.
No create, edit, or delete.

#### PaymentsRelationManager

Read-only. Shows: amount, currency, status, provider, paid_at, invoice_number.
Finance officer and above only (guarded in viewAny policy).
No create, edit, or delete.

---

### Step 9 — OfficerStatsWidget

File: `app/Filament/Officer/Widgets/OfficerStatsWidget.php`

Reads from officer_performance_metrics WHERE officer_id = auth()->id()
AND date >= now()->subDays(30). Falls back to live COUNT if no read model
data exists yet (first run before nightly job).

Stats to display:
- Assigned (currently active queue count — live query is OK for this single number)
- Completed (MTD from read model)
- Avg. turnaround (MTD from read model)
- SLA at risk (live scope: slaAtRisk() count)

---

### Step 10 — Team queue page

File: `app/Filament/Officer/Pages/TeamQueue.php`

Extends the base queue but removes the assigned_to_user_id scope.
Adds: filter by officer (SelectFilter on assigned_to_user_id).
Only accessible to senior_officer, admin, super_admin.
Gate check in mount(): abort_unless(auth()->user()->hasRole([...]), 403).

---

### Step 11 — SLA breaches page

File: `app/Filament/Officer/Pages/SlaBreaches.php`

Applies VisaApplication::slaBreached() scope.
Adds alert banner at top: "N applications have exceeded their SLA deadline."
Senior officer and above only.

---

### Step 12 — Appointments page

File: `app/Filament/Officer/Pages/Appointments.php`

Simple Filament table of appointments with filters: type, location, status, date range.
Actions: Mark completed, Mark missed, Cancel, Reschedule (opens modal with new date).
All actions call ScheduleAppointment or update appointment status directly via small
inline action (not a full domain action for simple status updates).

---

### Step 13 — My performance page

File: `app/Filament/Officer/Pages/OfficerPerformance.php`

Reads from officer_performance_metrics for auth()->id(), last 30 days.
Displays: Stats row (assigned, completed, avg turnaround, rework).
Table of daily rows for the period.
No write actions — read-only.

---

## Acceptance criteria — ALL must pass before this milestone is done

### Policy tests (most critical)

```php
// tests/Feature/Officer/VisaApplicationPolicyTest.php

it('case officer cannot view unassigned applications', function () { ... });
it('case officer cannot approve unassigned application', function () { ... });
it('senior officer can view all applications', function () { ... });
it('finance officer cannot accept or reject documents', function () { ... });
it('approve action is blocked when documents are missing', function () { ... });
it('approve action is blocked when document is infected', function () { ... });
it('reject action records reason and actor', function () { ... });
it('request info cannot unlock identity fields', function () {
    expect(fn() => RequestAdditionalInformation::run(
        $application, 'Please correct', [], ['personal.first_name'], 7
    ))->toThrow(\InvalidArgumentException::class);
});
it('document download writes audit log row', function () { ... });
it('internal notes are not visible in applicant portal scope', function () { ... });
```

### Feature tests

```php
// tests/Feature/Officer/DecisionActionsTest.php

it('approving application fires ApplicationApproved event', function () { ... });
it('rejecting application notifies applicant with reason', function () { ... });
it('requesting info transitions status to info_requested', function () { ... });
it('requesting info resets selected document status to pending', function () { ... });
it('scheduling appointment creates appointments row and dispatches PDF job', function () { ... });
it('duplicate approval attempt does not create duplicate status history', function () { ... });
```

### UI acceptance

- [ ] GET /officer redirects to login if unauthenticated
- [ ] GET /officer redirects to login if authenticated as applicant role
- [ ] Officer queue shows only assigned applications for case_officer
- [ ] Officer queue shows all applications for senior_officer
- [ ] Approve button is disabled when any required doc is not accepted
- [ ] Reject modal requires rejection reason (form validation)
- [ ] Request info modal does not list identity fields as unlockable
- [ ] Document view action generates signed URL (does not return public URL)
- [ ] php artisan test passes
- [ ] php artisan pint --test passes

---

## After completing the milestone

1. Run: `php artisan test`
2. Run: `php artisan pint --test`
3. List every file created or modified
4. Confirm all acceptance criteria above are satisfied
5. Note any assumptions or deferred work (add TODO comments in code)
6. Update the milestone checklist in CLAUDE.md row 5 to ☑

## What NOT to build in this milestone

- Admin panel changes (separate milestone)
- Reporting read model population job (milestone 7)
- Email notification templates (milestone 6)
- PDF generation jobs (milestone 6) — dispatch the events, but the
  listeners and PDF jobs will be wired in milestone 6
- Applicant portal respond-to-officer flow (separate Livewire work)
- Public tracking changes
- Bulk export of officer data
