# Database Model

Full table and column reference for the visa application system. Organised by milestone.

---

## Design Rules

| Rule | Implementation |
|---|---|
| ULIDs for sensitive models | All domain tables except `users` and `countries` |
| No public IDs in routes | `tracking_number` (opaque token) on `visa_applications` |
| Private storage only | `storage_path` on `document_versions` is a ULID-based S3 key |
| Original filename is metadata | Stored on `document_versions`, never used as a storage path |
| Immutable submitted snapshot | `application_snapshots` written once at submission, never updated |
| Ledger-style payments | Separate `payments`, `payment_items`, `invoices` — no single `paid` boolean |
| Idempotent webhooks | `payment_webhook_events.event_id` has a unique constraint |
| Status history append-only | `application_status_histories` has `created_at` only, no `updated_at` |
| Encrypted sensitive fields | Marked with `*` below |

## MySQL Notes

This project targets **MySQL 8+**. Key differences from the original PostgreSQL blueprint:

| Topic | Detail |
|---|---|
| JSON columns | Use `json` type — MySQL does not have `json`. Binary indexing is not available; add a generated column if a specific JSON path needs an index. |
| Charset | `utf8mb4` / `utf8mb4_unicode_ci` — already set in `config/database.php`. Required for full Unicode (emoji, non-Latin scripts). |
| ULIDs | Stored as `char(26)` — no charset issues; MySQL indexes `char` efficiently. |
| Booleans | `boolean` in migrations maps to `tinyint(1)` — Laravel handles this transparently. |
| Strict mode | MySQL strict mode is on by default in Laravel. Columns without defaults must always receive a value — no silent truncation. |
| JSON querying | Use `->whereJsonContains()`, `->whereJsonPath()`, and `json_extract()` syntax instead of PostgreSQL's `@>` / `->` operators. |

---

## Existing (Laravel scaffold)

### `users`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | Integer — not ULID; never exposed in public routes |
| name | string | |
| email | string unique | |
| email_verified_at | timestamp nullable | |
| password | string | |
| remember_token | string nullable | |
| created_at / updated_at | timestamps | |

### `password_reset_tokens`
| Column | Type |
|---|---|
| email | string PK |
| token | string |
| created_at | timestamp nullable |

### `sessions`
| Column | Type |
|---|---|
| id | string PK |
| user_id | bigint nullable index |
| ip_address | string(45) nullable |
| user_agent | text nullable |
| payload | longText |
| last_activity | integer index |

### `cache` / `cache_locks`
Standard Laravel cache table schema.

### `jobs` / `job_batches` / `failed_jobs`
Standard Laravel queue table schema.

---

## Milestone 1 — Identity, Roles & Config

### `applicant_profiles`
| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| user_id | bigint FK → users | unique |
| first_name | string | |
| last_name | string | |
| middle_name | string nullable | |
| date_of_birth | date | |
| gender | string | |
| nationality_id | bigint FK → countries | |
| country_of_residence_id | bigint FK → countries | |
| passport_number | string encrypted `*` | |
| passport_expiry_date | date | |
| phone | string encrypted `*` | |
| address_line_1 | string | |
| address_line_2 | string nullable | |
| city | string | |
| state | string nullable | |
| postal_code | string nullable | |
| created_at / updated_at | timestamps | |

### `countries`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | Integer — reference/lookup table |
| name | string | |
| iso2 | char(2) unique | |
| iso3 | char(3) unique | |
| phone_code | string nullable | |
| is_active | boolean default true | |
| created_at / updated_at | timestamps | |

### `visa_types`
| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| country_id | bigint FK → countries | Destination country |
| name | string | |
| code | string unique | e.g. `TOURIST_30` |
| description | text nullable | |
| processing_days | integer | |
| validity_days | integer | |
| max_entries | string | `single` \| `multiple` \| `unlimited` |
| is_active | boolean default true | |
| created_at / updated_at | timestamps | |

### `visa_fees`
| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| visa_type_id | char(26) FK → visa_types | |
| name | string | e.g. `Application Fee` |
| amount | integer | In cents |
| currency | char(3) | ISO 4217, e.g. `USD` |
| applicant_type | string default `all` | `all` \| `adult` \| `child` \| `senior` |
| effective_from | date | |
| effective_to | date nullable | Null = still active |
| is_active | boolean default true | |
| created_at / updated_at | timestamps | |

### Spatie Permission tables (auto-created by package)
`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`

Roles to seed: `applicant`, `agent`, `case_officer`, `senior_officer`, `document_verifier`, `finance_officer`, `support_staff`, `admin`, `super_admin`

---

## Milestone 2 — Application Workflow & Dynamic Forms

### `form_templates`
| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| visa_type_id | char(26) FK → visa_types | |
| name | string | |
| version | integer default 1 | |
| schema | json | Field definitions array |
| is_active | boolean default true | |
| published_at | timestamp nullable | |
| created_at / updated_at | timestamps | |

### `visa_applications`
| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| tracking_number | string unique | Opaque, non-sequential — used in public routes |
| applicant_profile_id | char(26) FK → applicant_profiles | |
| visa_type_id | char(26) FK → visa_types | |
| form_template_id | char(26) FK → form_templates | |
| status | string | See enum below |
| assigned_officer_id | bigint FK → users nullable | |
| submitted_at | timestamp nullable | |
| decision_at | timestamp nullable | |
| decision_reason | text nullable | |
| created_at / updated_at | timestamps | |

**Status enum:** `draft` | `submitted` | `payment_pending` | `payment_completed` | `under_review` | `additional_info_requested` | `approved` | `rejected` | `withdrawn`

### `application_answers`
| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| visa_application_id | char(26) FK → visa_applications | |
| field_key | string | Matches key in form_template schema |
| value | json | Handles all field types |
| created_at / updated_at | timestamps | |

Index: `(visa_application_id, field_key)` unique

### `application_snapshots`
Immutable. Written once when application is submitted. Decision letters are generated from this, not from live data.

| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| visa_application_id | char(26) FK → visa_applications | unique |
| snapshot_data | json | Full application state at submission |
| created_at | timestamp | No `updated_at` — immutable |

### `application_status_histories`
Append-only audit trail of every status transition.

| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| visa_application_id | char(26) FK → visa_applications | |
| from_status | string nullable | Null for initial creation |
| to_status | string | |
| actor_id | bigint FK → users | |
| reason | text nullable | |
| created_at | timestamp | No `updated_at` — append-only |

---

## Milestone 3 — Document Management

### `document_types`
| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| name | string | e.g. `Passport`, `Photograph` |
| description | text nullable | |
| accepted_mime_types | json | Array of allowed MIME strings |
| max_size_kb | integer | |
| max_pages | integer nullable | For multi-page documents |
| is_active | boolean default true | |
| created_at / updated_at | timestamps | |

### `visa_type_document_requirements`
| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| visa_type_id | char(26) FK → visa_types | |
| document_type_id | char(26) FK → document_types | |
| is_required | boolean default true | |
| display_order | integer default 0 | |
| notes | text nullable | Shown to applicant |
| created_at / updated_at | timestamps | |

### `application_documents`
One row per document slot per application. Points to the current accepted version.

| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| visa_application_id | char(26) FK → visa_applications | |
| document_type_id | char(26) FK → document_types | |
| current_version_id | char(26) FK → document_versions nullable | Set after first upload |
| status | string | See enum below |
| reviewed_by | bigint FK → users nullable | |
| reviewed_at | timestamp nullable | |
| rejection_reason | text nullable | |
| created_at / updated_at | timestamps | |

**Status enum:** `pending` | `uploaded` | `pending_scan` | `under_review` | `accepted` | `rejected` | `infected`

### `document_versions`
One row per upload or replacement. The storage path is always a ULID-based key, never the original filename.

| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| application_document_id | char(26) FK → application_documents | |
| storage_path | string | ULID-based private S3 key |
| original_filename | string | Metadata only — not used as path |
| mime_type | string | |
| file_size_bytes | integer | |
| sha256_checksum | string | Computed on upload |
| scan_status | string default `pending` | `pending` \| `clean` \| `infected` |
| scan_completed_at | timestamp nullable | |
| uploaded_by | bigint FK → users | |
| created_at | timestamp | No `updated_at` — immutable once uploaded |

---

## Milestone 4 — Payments & Invoices

### `payments`
| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| visa_application_id | char(26) FK → visa_applications | |
| status | string | See enum below |
| provider | string default `stripe` | |
| provider_payment_intent_id | string nullable | |
| provider_checkout_session_id | string nullable | |
| amount_subtotal | integer | In cents |
| amount_total | integer | In cents |
| currency | char(3) | ISO 4217 |
| failure_reason | string nullable | |
| succeeded_at | timestamp nullable | |
| created_at / updated_at | timestamps | |

**Status enum:** `pending` | `processing` | `succeeded` | `failed` | `refunded` | `partially_refunded`

### `payment_items`
| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| payment_id | char(26) FK → payments | |
| visa_fee_id | char(26) FK → visa_fees | |
| description | string | Snapshot of fee name at time of payment |
| quantity | integer default 1 | |
| unit_amount | integer | In cents |
| total_amount | integer | In cents |
| created_at / updated_at | timestamps | |

### `payment_webhook_events`
Idempotency table. Every incoming Stripe event is stored here before processing.

| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| provider | string default `stripe` | |
| event_id | string unique | Provider event ID — unique constraint prevents duplicates |
| event_type | string | e.g. `payment_intent.succeeded` |
| payload | json | Raw webhook body |
| processed_at | timestamp nullable | Null = not yet processed |
| processing_error | text nullable | |
| created_at | timestamp | |

### `invoices`
| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| payment_id | char(26) FK → payments | |
| invoice_number | string unique | Human-readable, sequential within scope |
| issued_at | timestamp | |
| pdf_storage_path | string nullable | Private S3 key; set after PDF job completes |
| created_at / updated_at | timestamps | |

---

## Cross-cutting

### `audit_logs`
Custom table for domain-level audit events (document preview, download, decision, etc.). Supplements Spatie `activity_log`.

| Column | Type | Notes |
|---|---|---|
| ulid | char(26) PK | |
| user_id | bigint FK → users nullable | Null for system/webhook actions |
| subject_type | string | Morph class name |
| subject_id | string | Morph ID |
| action | string | e.g. `document.downloaded`, `application.approved` |
| metadata | json | Contextual data for the action |
| ip_address | string(45) nullable | |
| user_agent | text nullable | |
| created_at | timestamp | No `updated_at` — append-only |

### `activity_log` (Spatie)
Auto-created by `spatie/laravel-activitylog`. Used for Eloquent model change tracking.

---

## Milestone 7 — Reporting (pre-aggregated)

These tables are written by scheduled jobs. Reports read from them — never from live aggregations.

### `daily_application_metrics`
| Column | Type |
|---|---|
| id | bigint PK |
| date | date |
| visa_type_id | char(26) FK → visa_types |
| submitted_count | integer |
| approved_count | integer |
| rejected_count | integer |
| pending_count | integer |
| avg_processing_days | decimal nullable |
| created_at / updated_at | timestamps |

Index: `(date, visa_type_id)` unique

### `daily_payment_metrics`
| Column | Type |
|---|---|
| id | bigint PK |
| date | date |
| currency | char(3) |
| total_collected | integer (cents) |
| total_refunded | integer (cents) |
| succeeded_count | integer |
| failed_count | integer |
| created_at / updated_at | timestamps |

Index: `(date, currency)` unique

### `officer_performance_metrics`
| Column | Type |
|---|---|
| id | bigint PK |
| date | date |
| officer_id | bigint FK → users |
| reviewed_count | integer |
| approved_count | integer |
| rejected_count | integer |
| info_requested_count | integer |
| avg_review_hours | decimal nullable |
| created_at / updated_at | timestamps |

Index: `(date, officer_id)` unique

### `document_rejection_metrics`
| Column | Type |
|---|---|
| id | bigint PK |
| date | date |
| document_type_id | char(26) FK → document_types |
| rejection_count | integer |
| top_reasons | json |
| created_at / updated_at | timestamps |

Index: `(date, document_type_id)` unique
