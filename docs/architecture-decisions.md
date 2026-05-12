# Architecture Decisions

Key decisions made for this project and the rationale behind them.

---

## ADR-001: Domain-Driven Folder Structure

**Decision:** All business logic lives in `app/Domain/` organized by bounded context. Controllers and Filament resources are thin wrappers that call domain Actions.

**Rationale:** Prevents bloated controllers. Actions are testable, single-purpose, and reusable across HTTP and queue contexts. Filament resources stay declarative.

**Rule:** Never put business logic in a controller, Filament resource, observer, or event listener.

---

## ADR-002: MySQL 8+ (switched from PostgreSQL)

**Decision:** Target MySQL 8+ instead of PostgreSQL.

**Rationale:** Operational preference. MySQL's `json` column type covers all use cases in this project. The one trade-off is no JSONB GIN indexing — if a specific JSON path needs an index, add a generated column.

**Consequence:** All migrations use `json`, not `jsonb`. Query builder uses `whereJsonContains()` and `whereJsonPath()` instead of PostgreSQL operators.

---

## ADR-003: ULIDs for Sensitive Primary Keys

**Decision:** All domain models handling applicant data use `char(26)` ULIDs as primary keys. Lookup/reference tables (`countries`) use auto-increment integers.

**Rationale:** ULIDs are URL-safe, time-sortable, and non-guessable. Prevents enumeration attacks on public-facing routes. `users` table keeps integer ID (Laravel default, never exposed publicly).

---

## ADR-004: Immutable Application Snapshot

**Decision:** When an application is submitted, a full snapshot of the form answers is written to `application_snapshots` and never updated.

**Rationale:** Decision letters, audit evidence, and legal compliance require the exact data the applicant submitted. Officers must review what was actually submitted, not data that may have changed since.

---

## ADR-005: Ledger-Style Payments

**Decision:** Payments are tracked as a ledger: `payments`, `payment_items`, `payment_webhook_events`, `invoices` are separate tables. No single `paid` boolean anywhere.

**Rationale:** Enables reconciliation, refund tracking, failed-attempt analysis, and idempotent webhook processing. A boolean is lossy — it can't represent retries, partial refunds, or provider-specific state.

---

## ADR-006: Private Document Storage Only

**Decision:** All applicant documents are stored on a private S3-compatible disk. No document ever touches a public bucket.

**Rationale:** Documents contain passport scans and photos — they must never be accidentally publicly accessible. Previews use signed, time-limited URLs generated server-side.

---

## ADR-007: Named Queues from Day One

**Decision:** Six named queues are configured at project start: `high`, `default`, `emails`, `documents`, `pdfs`, `reports`.

**Rationale:** Queue segregation prevents a slow PDF job from blocking a time-sensitive workflow transition. Horizon supervisors are configured per queue group with appropriate process counts and timeouts.

---

## ADR-008: PHPUnit (not Pest)

**Decision:** Tests are written as PHPUnit classes. Pest is not used.

**Rationale:** Laravel Boost guidelines and team preference. All tests use `php artisan make:test --phpunit`.

---

## ADR-009: Filament for Back-Office Portals

**Decision:** Both the admin portal and the officer portal are built with Filament 4 panels. The applicant-facing portal uses custom Blade + Livewire.

**Rationale:** Filament's resource/table/form primitives are highly productive for internal CRUD-heavy interfaces. The applicant portal needs custom UX that Filament's conventions don't suit.

---

## ADR-010: Spatie Packages for RBAC and Audit

**Decision:** `spatie/laravel-permission` for roles and permissions; `spatie/laravel-activitylog` for model change tracking, supplemented by a custom `audit_logs` table for domain-level events.

**Rationale:** Proven, widely-maintained packages with strong Laravel integration. Custom `audit_logs` is needed for domain actions (document download, officer decision) that are not simple model mutations.
