# visa-application — Project Workflow

A modular Laravel 12 monolith handling the full visa application lifecycle:
applicant registration → dynamic form → document upload → payment → officer review → decision → notification.

---

## Application Surfaces

| Surface | Implementation | Notes |
|---|---|---|
| Applicant portal | Custom Blade + Livewire | Registration, profile, application wizard, uploads, payments, tracking |
| Officer portal | Filament panel | Queue, assigned cases, document review, notes, decisions |
| Admin portal | Filament panel | Users, roles, countries, visa types, fees, form templates, reports |
| Public tracking | Plain Laravel route | Tracking number + OTP/token only — no raw database IDs |
| API layer | Add later with Sanctum | Only after all web flows are stable |

---

## Target Stack

| Area | Choice |
|---|---|
| Framework | Laravel 12, PHP 8.3+ |
| Admin UI | Filament 4 |
| Database | PostgreSQL (JSONB for form schemas) |
| Queue / cache | Redis + Laravel Horizon |
| Auth | Laravel starter kit (applicant portal); Filament auth (back-office panels) |
| RBAC | `spatie/laravel-permission` + Laravel Policies |
| Audit | `spatie/activitylog` + custom `audit_logs` table |
| PDF | Dompdf (MVP); Browsershot later for pixel-perfect output |
| Payments | Stripe Checkout / PaymentIntents |
| Storage | Private S3-compatible only — never public buckets |
| Primary keys | ULIDs for all sensitive models |
| AI tooling | Laravel Boost (install on day 0) |
| Testing | Pest |

> **Note on Laravel version:** The original blueprint referenced Laravel 13. This project targets **Laravel 12**. Pin all dependencies accordingly. Do not mix Laravel 13 syntax. Add an upgrade note for later.

---

## Repository Structure

```
docs/
  00-product-scope.md
  01-architecture-decisions.md
  02-database-model.md
  03-workflows-and-statuses.md
  04-security-rules.md
  05-filament-resource-map.md
  06-ai-agent-rules.md        ← AI agent guardrails (see section below)
features/
  001-foundation.md
  002-application-workflow.md
  003-documents.md
  004-payments.md
  005-officer-review.md
  006-reporting.md
AGENTS.md                     ← top-level AI agent instruction file
```

---

## Domain Folder Structure

All business logic lives in `app/Domain/`. Filament resources and controllers are thin wrappers that delegate to domain Actions. Never put business logic in controllers, Filament resources, or observers.

```
app/
  Domain/
    Identity/
      Actions/          # CreateApplicantProfile, UpdateProfileField
      Data/             # DTOs / value objects
      Events/
      Models/           # User, ApplicantProfile
      Policies/         # UserPolicy, ApplicantProfilePolicy
    Applications/
      Actions/          # CreateDraftApplication, UpdateApplicationSection,
                        #   SubmitApplication, GenerateTrackingNumber
      Enums/            # ApplicationStatus
      Events/
      Jobs/
      Models/           # VisaApplication, ApplicationAnswer,
                        #   ApplicationSnapshot, ApplicationStatusHistory, FormTemplate
      Policies/         # VisaApplicationPolicy
      Queries/
    Documents/
      Actions/          # UploadDocumentVersion, AcceptDocument, RejectDocument
      Enums/            # DocumentStatus
      Events/
      Jobs/
      Models/           # DocumentType, ApplicationDocument, DocumentVersion
      Policies/         # ApplicationDocumentPolicy
    Payments/
      Actions/          # CalculateApplicationFee, CreateCheckoutSession,
                        #   HandlePaymentWebhook, GenerateReceiptPdf
      Enums/            # PaymentStatus
      Events/
      Jobs/
      Models/           # Payment, PaymentItem, PaymentWebhookEvent, Invoice
      Webhooks/         # Stripe webhook handler
    Reporting/
      Actions/
      Exports/
      Jobs/
      Models/           # DailyApplicationMetrics, DailyPaymentMetrics, …
      Queries/
  Filament/
    Admin/
      Resources/
      Pages/
      Widgets/
    Officer/
      Resources/
      Pages/
      Widgets/
  Http/
    Controllers/        # Thin — delegate to domain Actions only
    Requests/
  Support/
    Money/
    Pdf/
    Security/
```

---

## Core Domain Actions

When implementing any feature, check this list before creating a new action:

**Identity:** `CreateApplicantProfile`, `UpdateProfileField`

**Applications:** `CreateDraftApplication`, `UpdateApplicationSection`, `SubmitApplication`, `GenerateTrackingNumber`

**Documents:** `UploadDocumentVersion`, `AcceptDocument`, `RejectDocument`

**Payments:** `CalculateApplicationFee`, `CreateCheckoutSession`, `HandlePaymentWebhook`, `GenerateReceiptPdf`

**Officer:** `AssignApplicationToOfficer`, `RequestAdditionalInformation`, `ScheduleAppointment`, `ApproveApplication`, `RejectApplication`

---

## Queue Configuration

Use separate named queues from day one — no slow PDF or email work in HTTP requests.

```
high        # time-sensitive workflow transitions
default     # general jobs
emails      # notification emails
documents   # document processing
pdfs        # PDF generation
reports     # exports and reporting jobs
```

---

## Security Rules (non-negotiable)

1. Every sensitive model must have a Policy. No exceptions.
2. Every custom Filament action must call `$this->authorize(...)` or `->authorize(...)` explicitly. Filament does not auto-authorize custom actions.
3. Never store applicant documents on a public disk. Always use a private S3-compatible disk. Generate signed, time-limited URLs for previews.
4. Never expose raw numeric IDs in public-facing routes. Use ULIDs or opaque tracking tokens.
5. Server-side document validation is mandatory: MIME type, extension, file size, and page/image limits. Do not rely solely on Filament's `FileUpload` component.
6. Store original filenames as metadata only. Use ULIDs for actual object storage paths.
7. Wrap all workflow state transitions in database transactions. Status changes, payment success, and decisions must be atomic.
8. Webhook idempotency is required. `HandlePaymentWebhook` must be idempotent — duplicate events must not create duplicate records.
9. Decision letters are generated from the immutable submitted snapshot, not from live application data.
10. Block document preview/download until scan status is clean (or an explicit, audited exception is made).

---

## Roles (to seed in Milestone 1)

```
applicant
agent
case_officer
senior_officer
document_verifier
finance_officer
support_staff
admin
super_admin
```

---

## Implementation Milestones

### Milestone 0 — Foundation

**Build:**
- Laravel 12 project with Filament 4 installed
- PostgreSQL and Redis configured
- Laravel Horizon configured
- Laravel Boost installed
- Pest test runner configured
- Laravel Pint code style configured
- `docs/` folder created
- `AGENTS.md` written with AI agent rules

**Acceptance criteria:**
- `php artisan test` passes
- `php artisan route:list` works
- `php artisan queue:work` runs locally
- Filament admin panel loads locally
- Filament production authorization is configured (required when `APP_ENV` is not `local`)
- AI agent has `AGENTS.md` instructions

---

### Milestone 1 — Identity, Roles & Base Admin

**Models:** `users`, `applicant_profiles`, `roles`, `permissions`, `countries`, `visa_types`, `visa_fees`

**Filament resources:** UserResource, ApplicantProfileResource, CountryResource, VisaTypeResource, VisaFeeResource

**Acceptance criteria:**
- Only admins can access admin and officer panels
- Applicants cannot access any back-office panel
- Profile fields can be created and edited by the owning applicant only
- Sensitive fields are encrypted or redacted where needed
- Every model has a Policy

---

### Milestone 2 — Application Workflow & Dynamic Forms

**Models:** `form_templates` (versioned JSON schema), `visa_applications`, `application_answers`, `application_snapshots`, `application_status_histories`

**Actions:** `CreateDraftApplication`, `UpdateApplicationSection`, `SubmitApplication`, `GenerateTrackingNumber`

**Form engine note:** Keep it simple at MVP. Admins edit raw JSON schema. No drag-and-drop form builder yet.

**Acceptance criteria:**
- Applicant can create one draft application
- Applicant can save each form section independently
- Applicant can submit only when all required fields are valid
- Submitted snapshot is immutable
- Application receives a non-sequential tracking number
- Status history is append-only
- Applicant cannot edit a submitted application unless the status explicitly allows corrections

---

### Milestone 3 — Document Management

**Models:** `document_types`, `visa_type_document_requirements`, `application_documents`, `document_versions`

**Rules:** private storage, ULID object paths, server-side MIME/extension/size validation, SHA-256 checksum on upload, audit log on every upload/preview/download/review action

**Acceptance criteria:**
- Required document rules are enforced before submission and before officer review
- Applicant can replace a rejected document
- Every replacement creates a new `document_versions` row; `application_documents` points to the current version
- Documents are never in public storage
- Unauthorized users cannot view or download documents
- Every document action writes an audit log entry

---

### Milestone 4 — Payments & Invoices

**Models:** `payments`, `payment_items`, `payment_webhook_events`, `invoices`

**Design:** Ledger-oriented. Never a single `paid` boolean. Track payment attempts, provider IDs, webhook events, line items, failure reasons, reconciliation state, and invoice records separately.

**Acceptance criteria:**
- Applicant can start a Stripe Checkout session for their application
- Payment amount is calculated from visa fee rules via `CalculateApplicationFee`
- Duplicate webhook events are idempotent — no duplicate records created
- Payment success transitions the application exactly once
- Failed payment leaves the application in `payment_pending`
- Receipt PDF uses payment ledger data, not mutable live values
- Finance role can view payments but cannot alter applications outside their policy

---

### Milestone 5 — Officer Review & Decisions

**Filament `VisaApplicationResource`:**
- Filters: status, visa type, country, assigned officer, submitted date
- Actions: assign, request info, schedule appointment, approve, reject
- Relation managers: documents, payments, notes, status history

**Acceptance criteria:**
- Officer sees only their assigned applications (unless senior officer or admin policy permits more)
- Officer cannot approve if required documents are missing, infected, pending, or rejected
- Every decision records: actor, timestamp, old status, new status, reason
- Decision letter is generated from the immutable submitted snapshot
- Applicant receives an email and database notification after every decision or info request

---

### Milestone 6 — Notifications, PDFs & Queues

**Build:**
- Database and email notifications for all applicant-facing events
- Queued PDF jobs: receipt, appointment confirmation, decision letter, application summary
- All queue names from the queue configuration section in active use
- Failed job visibility and retry strategy configured in Horizon

**Acceptance criteria:**
- Application submission dispatches notification and PDF jobs
- Payment success dispatches receipt PDF and notification jobs
- Document rejection dispatches applicant notification
- Failed jobs are visible in Horizon and retryable
- No slow PDF or email work blocks any HTTP request

---

### Milestone 7 — Reporting, Exports & Hardening

**Build (after core lifecycle is stable):**
- Pre-aggregated metrics tables: `daily_application_metrics`, `daily_payment_metrics`, `officer_performance_metrics`, `document_rejection_metrics`
- Queued CSV/XLSX exports written to private storage
- Filament dashboard widgets for admin and officer panels
- Audit log review interface
- Rate limiting on public and applicant-facing routes
- Security review and load tests

**Acceptance criteria:**
- Reports read from summary tables, not live aggregations
- Large exports are queued — never synchronous
- Export files are written to private storage
- Export access is authorized and audited
- Sensitive fields are redacted unless a compliance workflow explicitly allows disclosure

---

## MVP Definition of Done

The MVP is complete when this full flow works safely end-to-end, with tests:

1. Admin creates visa type, fee, form template, and document requirements
2. Applicant registers and verifies email
3. Applicant completes profile
4. Applicant creates a draft application
5. Applicant fills the dynamic form
6. Applicant uploads required documents
7. Applicant submits the application
8. Applicant pays through Stripe hosted checkout
9. Webhook marks payment succeeded (idempotently)
10. Receipt PDF is generated via queued job
11. Officer reviews the application in Filament
12. Officer accepts or rejects documents
13. Officer requests additional info or makes a final decision
14. Applicant receives email and in-app notifications
15. Decision PDF is generated from the immutable submitted snapshot
16. All sensitive actions are recorded in the audit log
17. Policy tests prove no user can access another user's data

---

## First Vertical Slice (Tourist Visa)

Before building all visa types and features, get one complete flow working:

- Tourist visa application only
- One dynamic form template
- Two required document types (passport, photo)
- Stripe payment integration
- Basic officer approve/reject
- Email notification on decision

Do not proceed to Milestone 7 or multi-visa-type support until this slice is fully tested and stable.

---

## What to Postpone (Post-MVP)

Do not let the AI agent touch these until the first vertical slice is fully working:

- Visual drag-and-drop form builder
- Multi-country complex rule engine
- Meilisearch / Algolia
- Read replicas or Laravel Octane
- Separate document-processing microservice
- Dedicated reporting database
- Complex refund workflows
- SMS / WhatsApp channels
- Partner API or mobile app
- Multi-region storage

---

## AI Agent Rules

Add these to `AGENTS.md` at the repo root and to `docs/06-ai-agent-rules.md`. Reference them in every task prompt.

```
You are working on a Laravel 12 + Filament 4 visa application system.

Rules:
1. Use the existing domain structure under app/Domain/.
2. Do not put business logic in controllers or Filament resources.
3. Every sensitive model must have a Policy.
4. Every custom Filament action must call authorization explicitly.
5. Never store applicant documents on a public disk.
6. Never expose raw numeric IDs in public tracking routes.
7. Use ULIDs for sensitive primary keys.
8. Wrap all workflow state transitions in database transactions.
9. Write or update tests with every feature.
10. Do not create unrelated refactors in the same PR/task.
11. Do not invent package APIs — check installed package docs through Laravel Boost.
12. Run php artisan test and report exact failures before declaring a task done.
```

### AI Agent Prompt Template

Use this structure for every implementation task:

```
Implement feature: [feature name]

Context:
- Laravel 12 + Filament 4
- Domain folder: app/Domain/
- Business logic in Actions only — not controllers or Filament resources
- PostgreSQL-compatible migrations
- ULIDs for sensitive primary keys
- Laravel Policies on every sensitive model
- Private S3-compatible storage for documents

Files to create or modify:
[list exact files if known]

Acceptance criteria:
[list testable criteria]

Security requirements:
[policy, audit, storage, and validation rules for this feature]

Tests required:
[unit / feature / policy / webhook tests]

After coding:
- Run: php artisan test
- Run: php artisan pint --test
- Explain changed files
- Explain any assumptions made
```

---

## Key References

- Laravel 12 docs: https://laravel.com/docs/12.x
- Filament 4 docs: https://filamentphp.com/docs
- Laravel Horizon docs: https://laravel.com/docs/12.x/horizon
- Spatie Laravel Permission: https://spatie.be/docs/laravel-permission
- Spatie Activitylog: https://spatie.be/docs/laravel-activitylog
- Laravel Boost (AI agent tooling): https://laravel.com/docs/12.x/ai
- Stripe PaymentIntents: https://stripe.com/docs/payments/payment-intents
