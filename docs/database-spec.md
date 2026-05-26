# VisaPortal — Database Specification

Derived from: `docs/screenshots/officer-dashboard-ui/dashboard-main.jpg`  
Stack: **Laravel 12 · PostgreSQL · ULIDs on all sensitive models · spatie/laravel-permission · spatie/activitylog**

---

## Design Principles

1. **ULIDs everywhere sensitive** — `visa_applications`, `users`, `applicant_profiles`, `documents`, `payments`, and all related tables use `ulid` primary keys. Junction / pivot tables use `bigIncrements`.
2. **Append-only audit trail** — status histories and audit logs are never updated or deleted.
3. **Immutable snapshots** — the submitted form state is frozen at submission time; decisions are generated from the snapshot, not live data.
4. **Ledger-style payments** — every payment attempt, webhook event, and line item gets its own row; amounts are stored in integer cents.
5. **Private storage only** — document paths are ULIDs, not original filenames; original filenames are stored as metadata.
6. **Soft deletes** — all user-facing models include `deleted_at`; hard deletes are forbidden outside of a compliance workflow.
7. **PostgreSQL-specific types** — `jsonb` for form schemas and snapshots; `text[]` array columns where appropriate.

---

## Domain Overview

```
Identity          users · applicant_profiles · officer_profiles
Reference Data    countries · visa_types · visa_fees · form_templates
Applications      visa_applications · application_answers · application_snapshots · application_status_histories · officer_notes
Documents         document_types · visa_type_document_requirements · application_documents · document_versions
Payments          payments · payment_items · payment_webhook_events · invoices
Reporting         daily_application_metrics · daily_payment_metrics · officer_performance_snapshots
Auth / RBAC       → managed by spatie/laravel-permission (roles · permissions · model_has_roles · model_has_permissions · role_has_permissions)
Audit             → managed by spatie/activitylog (activity_log) + custom audit_logs
Notifications     → Laravel notifications table (notifications)
```

---

## Table Definitions

---

### 1. `users`

Core authentication table for **all** user types (applicants, officers, admins).
Role assignment via spatie/laravel-permission determines which panel they access.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `char(26)` | PK | ULID |
| `name` | `varchar(255)` | NOT NULL | Display name |
| `email` | `varchar(255)` | NOT NULL, UNIQUE | |
| `email_verified_at` | `timestamptz` | NULLABLE | |
| `password` | `varchar(255)` | NOT NULL | Bcrypt |
| `remember_token` | `varchar(100)` | NULLABLE | |
| `officer_id` | `varchar(20)` | NULLABLE, UNIQUE | e.g. `OFF-001`; null for applicants |
| `is_active` | `boolean` | NOT NULL, DEFAULT true | Soft-disable without deletion |
| `last_login_at` | `timestamptz` | NULLABLE | |
| `deleted_at` | `timestamptz` | NULLABLE | Soft delete |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

**Indexes:** `email`, `officer_id`  
**Roles (seeded):** `applicant`, `agent`, `case_officer`, `senior_officer`, `document_verifier`, `finance_officer`, `support_staff`, `admin`, `super_admin`

---

### 2. `applicant_profiles`

Extended profile for users with the `applicant` role.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `char(26)` | PK | ULID |
| `user_id` | `char(26)` | NOT NULL, FK → users, UNIQUE | 1-to-1 |
| `date_of_birth` | `date` | NULLABLE | |
| `nationality` | `char(2)` | NULLABLE, FK → countries(iso2) | ISO 3166-1 alpha-2 |
| `passport_number` | `varchar(50)` | NULLABLE | Encrypted at rest |
| `passport_expiry` | `date` | NULLABLE | |
| `phone` | `varchar(30)` | NULLABLE | |
| `address_line_1` | `varchar(255)` | NULLABLE | |
| `address_line_2` | `varchar(255)` | NULLABLE | |
| `city` | `varchar(100)` | NULLABLE | |
| `postal_code` | `varchar(20)` | NULLABLE | |
| `country_of_residence` | `char(2)` | NULLABLE, FK → countries(iso2) | |
| `profile_completed_at` | `timestamptz` | NULLABLE | Set when all required fields are filled |
| `deleted_at` | `timestamptz` | NULLABLE | |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

**Indexes:** `user_id`, `nationality`, `country_of_residence`

---

### 3. `officer_profiles`

Extended profile for users with any officer/admin role.
Drives the **Team Workload** widget (initials, name, assigned count, capacity).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `char(26)` | PK | ULID |
| `user_id` | `char(26)` | NOT NULL, FK → users, UNIQUE | 1-to-1 |
| `display_initials` | `char(4)` | NOT NULL | e.g. `PM` — shown in avatar circle |
| `capacity` | `smallint` | NOT NULL, DEFAULT 12 | Max concurrent assignments |
| `specialisations` | `text[]` | NOT NULL, DEFAULT '{}' | e.g. `{Tourist, Business}` — shown in Team Workload |
| `avatar_color` | `varchar(30)` | NULLABLE | Tailwind colour token for avatar bg |
| `is_accepting_assignments` | `boolean` | NOT NULL, DEFAULT true | |
| `deleted_at` | `timestamptz` | NULLABLE | |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

**Indexes:** `user_id`  
**Dashboard widgets that read this table:** Team Workload, StatsOverview (assigned count)

---

### 4. `countries`

Reference table for nationality and residence lookups.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `smallint` | PK, AUTO | |
| `iso2` | `char(2)` | NOT NULL, UNIQUE | e.g. `IN` |
| `iso3` | `char(3)` | NOT NULL, UNIQUE | e.g. `IND` |
| `name` | `varchar(100)` | NOT NULL | |
| `is_active` | `boolean` | NOT NULL, DEFAULT true | |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

---

### 5. `visa_types`

Admin-managed catalogue of available visa products.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `char(26)` | PK | ULID |
| `country_id` | `smallint` | NOT NULL, FK → countries | Destination country |
| `code` | `varchar(30)` | NOT NULL | e.g. `TOURIST`, `WORK`, `BUSINESS` |
| `label` | `varchar(100)` | NOT NULL | Human-readable name |
| `max_stay_days` | `smallint` | NULLABLE | |
| `validity_days` | `smallint` | NULLABLE | |
| `is_active` | `boolean` | NOT NULL, DEFAULT true | |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

**Unique index:** `(country_id, code)`  
**Values visible in dashboard:** Tourist, Work, Business (Priority Queue → Type column)

---

### 6. `visa_fees`

Fee schedule per visa type. Versioned so historical invoices remain accurate.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `char(26)` | PK | ULID |
| `visa_type_id` | `char(26)` | NOT NULL, FK → visa_types | |
| `amount_cents` | `integer` | NOT NULL | Store in lowest denomination |
| `currency` | `char(3)` | NOT NULL, DEFAULT `'USD'` | ISO 4217 |
| `valid_from` | `date` | NOT NULL | |
| `valid_until` | `date` | NULLABLE | Open-ended if null |
| `created_by` | `char(26)` | NOT NULL, FK → users | |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

**Index:** `(visa_type_id, valid_from)`

---

### 7. `form_templates`

Admin-managed JSON schema used to generate the dynamic application form.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `char(26)` | PK | ULID |
| `visa_type_id` | `char(26)` | NOT NULL, FK → visa_types | |
| `version` | `smallint` | NOT NULL, DEFAULT 1 | Incremented on publish |
| `schema` | `jsonb` | NOT NULL | Field definitions |
| `is_published` | `boolean` | NOT NULL, DEFAULT false | Only one published per visa_type |
| `published_at` | `timestamptz` | NULLABLE | |
| `published_by` | `char(26)` | NULLABLE, FK → users | |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

**Unique partial index:** `(visa_type_id) WHERE is_published = true`

---

### 8. `visa_applications`

Core application table. Every card on the dashboard (Assigned to Me, Pending Queue, Priority Queue rows) reads from this table.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `char(26)` | PK | ULID — never exposed in public URLs |
| `tracking_number` | `varchar(20)` | NOT NULL, UNIQUE | Non-sequential opaque token e.g. `VA-2024-A1F3K2` |
| `applicant_id` | `char(26)` | NOT NULL, FK → users | |
| `visa_type_id` | `char(26)` | NOT NULL, FK → visa_types | |
| `form_template_id` | `char(26)` | NOT NULL, FK → form_templates | Version locked at submission |
| `assigned_officer_id` | `char(26)` | NULLABLE, FK → users | Drives "Assigned to Me" filter |
| `status` | `varchar(30)` | NOT NULL, DEFAULT `'draft'` | Enum — see Status Enum below |
| `priority` | `varchar(10)` | NOT NULL, DEFAULT `'normal'` | `low`, `normal`, `high` — drives Priority dot colour |
| `submitted_at` | `timestamptz` | NULLABLE | Set by SubmitApplication action |
| `decision_at` | `timestamptz` | NULLABLE | Set by Approve/Reject action |
| `decision_by` | `char(26)` | NULLABLE, FK → users | |
| `decision_reason` | `text` | NULLABLE | |
| `days_pending` | `smallint` | NULLABLE | Computed / denormalised for sorting; updated nightly |
| `deleted_at` | `timestamptz` | NULLABLE | |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

**Indexes:** `applicant_id`, `assigned_officer_id`, `status`, `submitted_at`, `(status, assigned_officer_id)`, `tracking_number`  

**Status Enum values:**

| Value | Meaning |
|---|---|
| `draft` | Created, not yet submitted |
| `submitted` | Submitted, awaiting payment |
| `payment_pending` | Fee invoice issued |
| `payment_failed` | Payment attempt failed |
| `paid` | Payment confirmed — enters review queue |
| `under_review` | Assigned to an officer |
| `additional_info_requested` | Officer requested more documents/info |
| `approved` | Final approval decision |
| `rejected` | Final rejection decision |
| `withdrawn` | Applicant withdrew |
| `expired` | Not completed within time limit |

**Dashboard widgets that read this table:**
- **Assigned to Me** stat → `WHERE assigned_officer_id = $me AND status IN ('under_review', 'additional_info_requested')`
- **Approved Today** stat → `WHERE decision_by = $me AND status = 'approved' AND date(decision_at) = today`
- **Pending Queue** stat → `WHERE status = 'paid' AND assigned_officer_id IS NULL`
- **Avg Decision Time** stat → `daily_application_metrics` (pre-aggregated)
- **Priority Queue** table → `WHERE status IN ('paid','under_review') ORDER BY submitted_at ASC LIMIT 10`

---

### 9. `application_answers`

Individual form field answers — rows, not a monolithic JSON blob, for queryability.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `bigint` | PK, AUTO | |
| `application_id` | `char(26)` | NOT NULL, FK → visa_applications | |
| `field_key` | `varchar(100)` | NOT NULL | Matches key in form_templates.schema |
| `value` | `text` | NULLABLE | Stored as string; cast at read time |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

**Unique index:** `(application_id, field_key)`

---

### 10. `application_snapshots`

Immutable copy of the full application state at submission. Decisions and PDFs are generated from this, never from live data.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `char(26)` | PK | ULID |
| `application_id` | `char(26)` | NOT NULL, FK → visa_applications, UNIQUE | One snapshot per application |
| `payload` | `jsonb` | NOT NULL | Full denormalised state at submission |
| `created_at` | `timestamptz` | NOT NULL | |

> This table has **no `updated_at`** — it is insert-only. Any UPDATE query must be rejected at the application layer via a policy check.

---

### 11. `application_status_histories`

Append-only log of every status transition.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `bigint` | PK, AUTO | |
| `application_id` | `char(26)` | NOT NULL, FK → visa_applications | |
| `from_status` | `varchar(30)` | NULLABLE | Null on first insert (draft created) |
| `to_status` | `varchar(30)` | NOT NULL | |
| `actor_id` | `char(26)` | NULLABLE, FK → users | Null for system-triggered transitions |
| `reason` | `text` | NULLABLE | Required for rejection |
| `metadata` | `jsonb` | NULLABLE | e.g. payment provider reference |
| `created_at` | `timestamptz` | NOT NULL | |

**Index:** `application_id`  
**Dashboard widget:** Today's Activity feed reads the last 3 rows for the current officer

---

### 12. `officer_notes`

Internal officer notes attached to an application. Not visible to applicants.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `char(26)` | PK | ULID |
| `application_id` | `char(26)` | NOT NULL, FK → visa_applications | |
| `author_id` | `char(26)` | NOT NULL, FK → users | |
| `body` | `text` | NOT NULL | |
| `is_pinned` | `boolean` | NOT NULL, DEFAULT false | |
| `deleted_at` | `timestamptz` | NULLABLE | |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

**Index:** `application_id`

---

### 13. `document_types`

Admin-defined catalogue of document types (Passport, Bank Statement, etc.).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `char(26)` | PK | ULID |
| `code` | `varchar(50)` | NOT NULL, UNIQUE | e.g. `PASSPORT`, `BANK_STATEMENT` |
| `label` | `varchar(100)` | NOT NULL | |
| `allowed_mime_types` | `text[]` | NOT NULL | e.g. `{application/pdf,image/jpeg}` |
| `max_size_kb` | `integer` | NOT NULL | |
| `max_pages` | `smallint` | NULLABLE | |
| `is_active` | `boolean` | NOT NULL, DEFAULT true | |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

---

### 14. `visa_type_document_requirements`

Which document types are required for a given visa type.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `bigint` | PK, AUTO | |
| `visa_type_id` | `char(26)` | NOT NULL, FK → visa_types | |
| `document_type_id` | `char(26)` | NOT NULL, FK → document_types | |
| `is_mandatory` | `boolean` | NOT NULL, DEFAULT true | |
| `notes` | `text` | NULLABLE | Officer-facing guidance |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

**Unique index:** `(visa_type_id, document_type_id)`

---

### 15. `application_documents`

One row per required document slot per application.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `char(26)` | PK | ULID |
| `application_id` | `char(26)` | NOT NULL, FK → visa_applications | |
| `document_type_id` | `char(26)` | NOT NULL, FK → document_types | |
| `status` | `varchar(20)` | NOT NULL, DEFAULT `'pending'` | `pending`, `uploaded`, `accepted`, `rejected`, `infected` |
| `reviewed_by` | `char(26)` | NULLABLE, FK → users | Officer who last reviewed |
| `reviewed_at` | `timestamptz` | NULLABLE | |
| `rejection_reason` | `text` | NULLABLE | |
| `deleted_at` | `timestamptz` | NULLABLE | |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

**Unique index:** `(application_id, document_type_id) WHERE deleted_at IS NULL`  
**Index:** `status`

---

### 16. `document_versions`

Each upload creates a new version row. The latest is `current = true`.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `char(26)` | PK | ULID |
| `application_document_id` | `char(26)` | NOT NULL, FK → application_documents | |
| `storage_path` | `varchar(500)` | NOT NULL | ULID-based path; never the original filename |
| `original_filename` | `varchar(255)` | NOT NULL | Metadata only |
| `mime_type` | `varchar(100)` | NOT NULL | |
| `size_bytes` | `integer` | NOT NULL | |
| `checksum_sha256` | `char(64)` | NOT NULL | |
| `scan_status` | `varchar(20)` | NOT NULL, DEFAULT `'pending'` | `pending`, `clean`, `infected` |
| `scan_completed_at` | `timestamptz` | NULLABLE | |
| `is_current` | `boolean` | NOT NULL, DEFAULT true | Only one current per document slot |
| `uploaded_by` | `char(26)` | NOT NULL, FK → users | |
| `created_at` | `timestamptz` | NOT NULL | |

**Partial unique index:** `(application_document_id) WHERE is_current = true`

---

### 17. `payments`

One payment attempt record per checkout session.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `char(26)` | PK | ULID |
| `application_id` | `char(26)` | NOT NULL, FK → visa_applications | |
| `payer_id` | `char(26)` | NOT NULL, FK → users | |
| `provider` | `varchar(30)` | NOT NULL, DEFAULT `'stripe'` | |
| `provider_checkout_id` | `varchar(255)` | NULLABLE, UNIQUE | Stripe Checkout Session ID |
| `provider_payment_intent_id` | `varchar(255)` | NULLABLE, UNIQUE | Stripe PaymentIntent ID |
| `status` | `varchar(20)` | NOT NULL, DEFAULT `'pending'` | `pending`, `succeeded`, `failed`, `refunded`, `partially_refunded` |
| `amount_cents` | `integer` | NOT NULL | |
| `currency` | `char(3)` | NOT NULL, DEFAULT `'USD'` | |
| `failure_reason` | `text` | NULLABLE | |
| `succeeded_at` | `timestamptz` | NULLABLE | |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

**Indexes:** `application_id`, `provider_checkout_id`, `provider_payment_intent_id`, `status`

---

### 18. `payment_items`

Line items for each payment (fee breakdown).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `bigint` | PK, AUTO | |
| `payment_id` | `char(26)` | NOT NULL, FK → payments | |
| `description` | `varchar(255)` | NOT NULL | |
| `quantity` | `smallint` | NOT NULL, DEFAULT 1 | |
| `unit_amount_cents` | `integer` | NOT NULL | |
| `created_at` | `timestamptz` | NOT NULL | |

---

### 19. `payment_webhook_events`

Idempotent log of every incoming Stripe webhook event.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `bigint` | PK, AUTO | |
| `provider` | `varchar(30)` | NOT NULL | |
| `event_id` | `varchar(255)` | NOT NULL, UNIQUE | Provider event ID — used for idempotency check |
| `event_type` | `varchar(100)` | NOT NULL | e.g. `payment_intent.succeeded` |
| `payload` | `jsonb` | NOT NULL | Raw webhook body |
| `processed_at` | `timestamptz` | NULLABLE | Null = not yet processed |
| `failed_at` | `timestamptz` | NULLABLE | |
| `failure_reason` | `text` | NULLABLE | |
| `created_at` | `timestamptz` | NOT NULL | |

---

### 20. `invoices`

PDF receipt metadata. The actual file lives in private S3.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `char(26)` | PK | ULID |
| `payment_id` | `char(26)` | NOT NULL, FK → payments, UNIQUE | 1-to-1 |
| `invoice_number` | `varchar(30)` | NOT NULL, UNIQUE | Sequential per year e.g. `INV-2024-00042` |
| `storage_path` | `varchar(500)` | NOT NULL | Private S3 path |
| `generated_at` | `timestamptz` | NOT NULL | |
| `created_at` | `timestamptz` | NOT NULL | |

---

### 21. `daily_application_metrics`

Pre-aggregated by a nightly scheduled job. Dashboard stat cards read from here — never from live aggregations.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `bigint` | PK, AUTO | |
| `date` | `date` | NOT NULL | |
| `officer_id` | `char(26)` | NULLABLE, FK → users | Null = system-wide totals |
| `visa_type_id` | `char(26)` | NULLABLE, FK → visa_types | Null = all types |
| `submitted_count` | `integer` | NOT NULL, DEFAULT 0 | |
| `approved_count` | `integer` | NOT NULL, DEFAULT 0 | |
| `rejected_count` | `integer` | NOT NULL, DEFAULT 0 | |
| `avg_decision_days` | `numeric(5,2)` | NULLABLE | **Drives "Avg. Decision Time" stat card** |
| `pending_count` | `integer` | NOT NULL, DEFAULT 0 | **Drives "Pending Queue" stat card** |
| `created_at` | `timestamptz` | NOT NULL | |

**Unique index:** `(date, officer_id, visa_type_id)`  
**Dashboard widget:** StatsOverview reads `WHERE date = today AND officer_id = $me`

---

### 22. `daily_payment_metrics`

Pre-aggregated payment totals for the Reporting panel.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `bigint` | PK, AUTO | |
| `date` | `date` | NOT NULL | |
| `currency` | `char(3)` | NOT NULL | |
| `total_collected_cents` | `bigint` | NOT NULL, DEFAULT 0 | |
| `total_refunded_cents` | `bigint` | NOT NULL, DEFAULT 0 | |
| `successful_payments` | `integer` | NOT NULL, DEFAULT 0 | |
| `failed_payments` | `integer` | NOT NULL, DEFAULT 0 | |
| `created_at` | `timestamptz` | NOT NULL | |

**Unique index:** `(date, currency)`

---

### 23. `officer_performance_snapshots`

Weekly snapshot of each officer's KPIs for My Performance page.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `bigint` | PK, AUTO | |
| `officer_id` | `char(26)` | NOT NULL, FK → users | |
| `week_start` | `date` | NOT NULL | Monday of the week |
| `assigned_count` | `integer` | NOT NULL, DEFAULT 0 | |
| `approved_count` | `integer` | NOT NULL, DEFAULT 0 | |
| `rejected_count` | `integer` | NOT NULL, DEFAULT 0 | |
| `info_requested_count` | `integer` | NOT NULL, DEFAULT 0 | |
| `avg_decision_days` | `numeric(5,2)` | NULLABLE | |
| `created_at` | `timestamptz` | NOT NULL | |

**Unique index:** `(officer_id, week_start)`

---

### 24. `audit_logs`

Custom high-fidelity audit table (supplements spatie/activitylog for sensitive operations).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | `bigint` | PK, AUTO | |
| `actor_id` | `char(26)` | NULLABLE, FK → users | Null for system actions |
| `actor_ip` | `inet` | NULLABLE | |
| `event` | `varchar(100)` | NOT NULL | e.g. `document.downloaded`, `application.approved` |
| `auditable_type` | `varchar(100)` | NOT NULL | Morph type |
| `auditable_id` | `char(26)` | NOT NULL | Morph ID |
| `old_values` | `jsonb` | NULLABLE | |
| `new_values` | `jsonb` | NULLABLE | |
| `metadata` | `jsonb` | NULLABLE | e.g. `{signed_url_expires: ...}` |
| `created_at` | `timestamptz` | NOT NULL | |

**Indexes:** `(auditable_type, auditable_id)`, `actor_id`, `event`, `created_at`

---

## Relationship Diagram (text)

```
users ──┬──< applicant_profiles
        ├──< officer_profiles
        ├──< visa_applications (as applicant_id)
        ├──< visa_applications (as assigned_officer_id)
        ├──< application_status_histories (as actor_id)
        ├──< officer_notes (as author_id)
        ├──< document_versions (as uploaded_by)
        ├──< payments (as payer_id)
        └──< audit_logs (as actor_id)

countries ──< visa_types ──┬──< visa_fees
                            ├──< form_templates
                            ├──< visa_type_document_requirements >──< document_types
                            └──< visa_applications ──┬──< application_answers
                                                      ├── application_snapshots (1-to-1)
                                                      ├──< application_status_histories
                                                      ├──< officer_notes
                                                      ├──< application_documents ──< document_versions
                                                      └──< payments ──┬──< payment_items
                                                                       ├──< payment_webhook_events
                                                                       └── invoices (1-to-1)
```

---

## Migration Order

Run in this sequence to satisfy foreign key constraints:

1. `users`
2. `countries`
3. `applicant_profiles`
4. `officer_profiles`
5. `visa_types`
6. `visa_fees`
7. `form_templates`
8. `document_types`
9. `visa_type_document_requirements`
10. `visa_applications`
11. `application_answers`
12. `application_snapshots`
13. `application_status_histories`
14. `officer_notes`
15. `application_documents`
16. `document_versions`
17. `payments`
18. `payment_items`
19. `payment_webhook_events`
20. `invoices`
21. `daily_application_metrics`
22. `daily_payment_metrics`
23. `officer_performance_snapshots`
24. `audit_logs`
25. spatie/laravel-permission tables (run `php artisan permission:setup-teams` if multi-team needed)
26. spatie/activitylog table (`php artisan activitylog:table`)
27. Laravel notifications (`php artisan notifications:table`)

---

## Seeder Requirements

| Seeder | Contents |
|---|---|
| `CountrySeeder` | All ISO 3166-1 alpha-2 countries |
| `VisaTypeSeeder` | Tourist, Work, Business, Medical, Transit, Student types per relevant country |
| `DocumentTypeSeeder` | Passport, Bank Statement, Employment Letter, Insurance, Flight Itinerary, etc. |
| `RolePermissionSeeder` | All 9 roles + permission matrix |
| `OfficerSeeder` | Sample officers with profiles matching screenshot (Priya Mehta PM, Rahul Sharma RS, Anita Desai AD, Mohammed Khan MK) |

---

## Encryption Requirements

The following columns must be encrypted at rest using Laravel's `Crypt` facade or an encrypted cast:

- `applicant_profiles.passport_number`
- `applicant_profiles.date_of_birth`
- `application_snapshots.payload` (contains PII)

---

## Key Constraints Summary

| Rule | Tables |
|---|---|
| No hard delete | `users`, `visa_applications`, `application_documents`, `officer_notes` |
| Insert-only (no UPDATE/DELETE) | `application_snapshots`, `application_status_histories`, `audit_logs`, `payment_webhook_events` |
| Idempotency key | `payment_webhook_events.event_id` UNIQUE |
| Only one current version | `document_versions` partial unique on `(application_document_id) WHERE is_current = true` |
| Only one published template | `form_templates` partial unique on `(visa_type_id) WHERE is_published = true` |
| Amounts always in cents | `payments`, `payment_items`, `visa_fees`, all metric tables |
