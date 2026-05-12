Recommended plan of action
Treat the uploaded blueprint as the architecture baseline, but convert it into a smaller, AI-agent-friendly implementation backlog. The best direction is: Laravel 12 modular monolith + PostgreSQL + Redis queues + private S3-compatible storage + Filament for officer/admin operations + a custom applicant portal. The document’s main recommendation is a modular monolith with strict domain boundaries, private documents, RBAC/policies, audit trails, queues, payments, PDF generation, reporting, and administration.
One adjustment: your document recommends Laravel 13.x, while you plan to use Laravel 12. That is workable, but pin the stack clearly and make the AI agent use Laravel 12 documentation only. Laravel’s own Laravel 12 docs currently show it as an older version and suggest upgrading to Laravel 13, so create an upgrade note for later rather than mixing Laravel 13 syntax into this build. (Laravel)
1. Decide the application surfaces first
   Use Filament for back-office, not necessarily for every user-facing screen.
   Surface	Recommended implementation	Notes
   Applicant portal	Custom Laravel Blade/Livewire pages, or a dedicated Filament applicant panel only if you want internal-style UI	Best for registration, profile, application wizard, uploads, payments, tracking.
   Officer portal	Filament panel	Queue, assigned cases, document review, notes, appointment scheduling, decisions.
   Admin portal	Filament panel	Users, roles, permissions, countries, visa types, fees, form templates, document rules, reports.
   Public tracking	Plain Laravel route/controller	Tracking number + DOB/email OTP/secure token. No raw database IDs.
   API layer	Add later with Sanctum	Only after web flows are stable.
   Filament is well suited for resources, forms, tables, widgets, actions, notifications, and dashboards, but its own security docs stress that custom actions, custom pages, custom components, and non-standard business logic must be authorized by you, not assumed to be protected automatically. (Filament) (Filament)
2. Create an AI-agent implementation workspace
   Before writing features, create these project docs inside the repo:
   docs/
   00-product-scope.md
   01-architecture-decisions.md
   02-database-model.md
   03-workflows-and-statuses.md
   04-security-rules.md
   05-filament-resource-map.md
   06-ai-agent-rules.md
   features/
   001-foundation.md
   002-application-workflow.md
   003-documents.md
   004-payments.md
   005-officer-review.md
   006-reporting.md
   Install Laravel Boost for AI-agent collaboration. Laravel’s documentation says Boost supports Laravel 10, 11, and 12, provides AI agents with app insight, routes, schema, logs, docs search, Tinker access, and version-aware Laravel guidance. Filament also recommends Boost for AI-assisted Filament development. (Laravel) (Filament)
   Use this rule for the AI agent: one feature ticket at a time, tests required, no unrelated refactors, no security shortcuts, no public document storage, no skipping policies.
3. Use this target stack
   Area	Recommendation
   Laravel	Laravel 12, PHP 8.3 or 8.4, Composer-pinned dependencies
   Admin UI	Filament, preferably current supported major compatible with your Laravel 12 install
   Database	PostgreSQL preferred because of JSONB and strong indexing; MySQL acceptable if simpler
   Queue/cache	Redis
   Queue monitoring	Laravel Horizon
   Auth	Laravel built-in auth/starter kit for applicant portal; Filament auth for panels if suitable
   Roles/permissions	spatie/laravel-permission plus Laravel policies
   Audit	Custom audit_logs table and/or Spatie Activitylog
   Payments	Stripe Checkout/PaymentIntents or local gateway equivalent
   PDFs	Dompdf first; Browsershot later for pixel-perfect documents
   Documents	Private S3-compatible storage; no public bucket
   Search	Database search first; Scout/Meilisearch later
   Testing	Pest or PHPUnit, plus policy, feature, webhook, upload, and workflow tests
   For queues, use Redis and Horizon early because the uploaded blueprint depends heavily on background jobs for PDFs, emails, document processing, payment webhooks, exports, and reporting. Laravel Horizon’s docs state that it monitors Redis queue throughput, runtime, failures, and keeps worker configuration in source-controlled config. (Laravel)
4. Start with the vertical slice, not the full system
   The document’s “recommended first implementation slice” is the right starting point: registration, applicant profile, visa type setup, one form template, draft save, document upload, submission, payment checkout, webhook success, receipt PDF, and basic officer review.
   Build only one visa flow first, for example:
   Tourist visa application
   Applicant registers
   Applicant completes profile
   Applicant starts application
   Applicant fills one dynamic form
   Applicant uploads passport and photo
   Applicant submits application
   Applicant pays fee
   Webhook marks payment succeeded
   Receipt PDF is generated
   Officer sees application in queue
   Officer accepts/rejects document
   Officer requests info or approves/rejects
   Applicant receives notification
   Do not start with all visa types, all reports, all dashboards, SMS, multi-country rules, or advanced search.
5. Implementation milestones
   Milestone 0 — Planning and repository setup
   Deliverables:
   Laravel 12 project
   Filament installed
   Database configured
   Redis configured
   Queue worker local setup
   Laravel Boost installed
   Code style and test runner configured
   docs/ folder created
   AI-agent rules written
   Acceptance criteria:
   php artisan test passes
   php artisan route:list works
   php artisan queue:work can run locally
   Filament admin panel loads locally
   AI agent has AGENTS.md or equivalent instructions
   Also configure Filament production rules from the beginning. Filament requires production panel authorization when APP_ENV is not local, and it recommends deployment optimization commands such as php artisan filament:optimize and Laravel’s php artisan optimize. (Filament)
   Milestone 1 — Identity, roles, and base admin
   Build:
   users
   applicant_profiles
   roles
   permissions
   countries
   visa_types
   visa_fees
   basic audit logging
   Filament UserResource
   Filament ApplicantProfileResource
   Filament CountryResource
   Filament VisaTypeResource
   Filament VisaFeeResource
   Roles to seed:
   applicant
   agent
   case_officer
   senior_officer
   document_verifier
   finance_officer
   support_staff
   admin
   super_admin
   Acceptance criteria:
   Only admins can access admin panel.
   Applicants cannot access officer/admin panels.
   Profile fields can be created and edited by the owner.
   Sensitive fields are encrypted or redacted where needed.
   Every sensitive model has a policy.
   Milestone 2 — Application workflow and dynamic forms
   Build:
   form_templates
   visa_applications
   application_answers
   application_snapshots
   application_status_histories
   application status enum
   public status mapper
   tracking number generator
   draft save
   section update
   submit application
   Keep the dynamic form engine simple at first. Store the form schema as versioned JSON. Do not build a complex drag-and-drop form builder in the MVP. Admins can paste/edit validated JSON first; a richer form builder can come later.
   Acceptance criteria:
   Applicant can create one draft application.
   Applicant can save each form section.
   Applicant can submit only when required fields are valid.
   Submitted snapshot is immutable.
   Application gets a non-sequential tracking number.
   Status history is append-only.
   Applicant cannot edit submitted application unless status allows corrections.
   Milestone 3 — Document management
   Build:
   document_types
   visa_type_document_requirements
   application_documents
   document_versions
   secure upload flow
   private storage disk
   checksum calculation
   document status enum
   document review actions
   document rejection reason
   audit events for upload/preview/download/review
   Important implementation rule: do not rely only on Filament FileUpload for security. Validate MIME type, extension, size, and page/image limits server-side. Store original filenames only as metadata. Generate object paths using ULIDs. Block officer preview/download until the scan status is clean or until you intentionally allow a controlled exception.
   Acceptance criteria:
   Required document rules are enforced before submission or before review.
   Applicant can replace a rejected document.
   Every replacement creates a new document_versions row.
   application_documents points to the current version.
   Documents are never stored in public storage.
   Unauthorized users cannot view or download documents.
   Every document action writes an audit log.
   Milestone 4 — Payments and invoices
   Build:
   payments
   payment_items
   payment_webhook_events
   invoices
   fee calculator
   checkout session creation
   webhook signature verification
   webhook idempotency
   payment success transition
   receipt PDF generation
   finance/admin payment views
   Payment design should be ledger-oriented. Avoid a single paid boolean. Keep attempts, provider IDs, webhook events, line items, failure reasons, reconciliation state, and invoice records.
   Acceptance criteria:
   Applicant can start checkout for an application.
   Payment amount is calculated from visa fee rules.
   Duplicate webhook does not duplicate payment records.
   Payment success transitions application once only.
   Failed payment keeps application in payment_pending.
   Receipt PDF uses payment ledger data, not mutable live values.
   Finance role can view payments but cannot alter applications outside policy.
   Milestone 5 — Officer review and decisions
   Build:
   officer queue
   assignment
   review_notes
   request additional information
   document accept/reject
   appointment scheduling
   approve application
   reject application
   decision metadata
   decision PDF
   applicant notifications
   Filament mapping:
   VisaApplicationResource
   filters: status, visa type, country, assigned officer, submitted date
   actions: assign, request info, schedule appointment, approve, reject
   relation managers: documents, payments, notes, status history

ApplicationDocumentResource or relation manager
actions: preview, accept, reject, request resubmission

PaymentResource
actions: inspect webhook history, mark reconciliation notes, refund request workflow later
Acceptance criteria:
Officer sees only assigned applications unless senior/admin policy allows more.
Officer cannot approve if required documents are missing, infected, pending, or rejected.
Every decision records actor, timestamp, old/new status, and reason where needed.
Decision letter is generated from immutable submitted snapshot.
Applicant receives email/database notification after decision or info request.
Milestone 6 — Notifications, PDFs, and queues
Build:
database notifications
email notifications
queued PDF jobs
application summary PDF
receipt PDF
appointment PDF
decision PDF
failed job visibility
retry strategy
Use separate queues early:
high
default
emails
documents
pdfs
reports
Acceptance criteria:
Submitting an application dispatches notification and PDF jobs.
Payment success dispatches receipt PDF and notification jobs.
Document rejection dispatches applicant notification.
Failed jobs are visible and retryable.
No slow PDF/email work blocks HTTP requests.
Milestone 7 — Reporting, exports, and operational hardening
Build after the core lifecycle is stable:
daily_application_metrics
daily_payment_metrics
officer_performance_metrics
document_rejection_metrics
queued CSV/XLSX exports
dashboard widgets
audit log review
backup checks
rate limiting
security review
load tests
Acceptance criteria:
Reports read from summary tables or read models, not heavy live aggregations.
Large exports are queued.
Exports are written to private storage.
Export access is authorized and audited.
Sensitive fields are redacted unless compliance workflow allows disclosure.
6. Domain structure for Laravel 12
   Use the uploaded document’s domain-oriented design, but keep it practical:
   app/
   Domain/
   Identity/
   Actions/
   Data/
   Events/
   Models/
   Policies/
   Applications/
   Actions/
   Enums/
   Events/
   Jobs/
   Models/
   Policies/
   Queries/
   Documents/
   Actions/
   Enums/
   Events/
   Jobs/
   Models/
   Policies/
   Payments/
   Actions/
   Enums/
   Events/
   Jobs/
   Models/
   Webhooks/
   Reporting/
   Actions/
   Exports/
   Jobs/
   Models/
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
   Controllers/
   Requests/
   Support/
   Money/
   Pdf/
   Security/
   The AI agent should create actions for business use cases, not put business logic in controllers or Filament resources.
   Examples:
   CreateDraftApplication
   UpdateApplicationSection
   SubmitApplication
   GenerateTrackingNumber
   UploadDocumentVersion
   AcceptDocument
   RejectDocument
   CalculateApplicationFee
   CreateCheckoutSession
   HandlePaymentWebhook
   GenerateReceiptPdf
   AssignApplicationToOfficer
   ApproveApplication
   RejectApplication
7. Guardrails for working with the AI agent
   Give the agent this rule set before each task:
   You are working on a Laravel 12 + Filament visa application system.

Rules:
1. Use existing domain structure.
2. Do not put business logic in controllers or Filament resources.
3. Every sensitive model must have a policy.
4. Every custom Filament action must call authorization explicitly.
5. Never store applicant documents on a public disk.
6. Never expose raw numeric IDs in public tracking.
7. Use ULIDs for sensitive primary keys.
8. Use database transactions for workflow transitions.
9. Write or update tests with every feature.
10. Do not create unrelated refactors.
11. Do not invent package APIs; check installed package docs through Laravel Boost.
12. Run tests and report exact failures.
    A good implementation prompt template:
    Implement feature: [feature name]

Context:
- Laravel 12
- Filament back-office
- Domain folder structure already exists
- Use PostgreSQL-compatible migrations
- Use ULIDs
- Use policies
- Use actions for business logic

Files to create or modify:
[list exact files if known]

Acceptance criteria:
[list testable criteria]

Security requirements:
[list policy, audit, storage, validation rules]

Tests required:
[list unit/feature/policy/webhook tests]

After coding:
- Run php artisan test
- Run php artisan pint --test
- Explain changed files
- Explain any assumptions
1. What to postpone until after MVP
   Postpone these until the first full vertical slice works:
   Advanced visual form builder
   Multi-country complex rule engine
   Meilisearch/Algolia
   Read replicas
   Octane
   Separate document-processing service
   Dedicated reporting database
   Complex refund workflows
   SMS/WhatsApp channels
   Partner API
   Mobile app
   Multi-region storage
   The uploaded blueprint already describes later scaling phases, but for the first build, one Laravel app with SQL database, Redis, private storage, queue workers, scheduler, mail provider, and payment gateway is enough.
2. Definition of done for the MVP
   The MVP is done when this full flow works safely:
   Admin creates visa type, fee, form template, and document requirements.
   Applicant registers and verifies email.
   Applicant completes profile.
   Applicant creates draft application.
   Applicant fills dynamic form.
   Applicant uploads required documents.
   Applicant submits application.
   Applicant pays through hosted checkout.
   Webhook marks payment succeeded idempotently.
   Receipt PDF is generated.
   Officer reviews application in Filament.
   Officer accepts/rejects documents.
   Officer requests info or makes decision.
   Applicant receives notifications.
   Decision PDF is generated from immutable snapshot.
   All sensitive actions are audited.
   Policy tests prove users cannot access others’ data.
   That gives you a controlled foundation for expanding visa types, reporting, finance reconciliation, and advanced administration without letting the AI agent generate a large, fragile system all at once.

