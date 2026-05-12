# AI Agent Rules

Rules for AI agents working on this codebase. These mirror `AGENTS.md` at the repo root.

## Context

You are working on a Laravel 12 + Filament 4 visa application system with MySQL 8+, Redis, and Laravel Horizon.

## Rules

1. Use the existing domain structure under `app/Domain/`. Do not put business logic in controllers or Filament resources.
2. Every sensitive model must have a Policy. No exceptions.
3. Every custom Filament action must call `->authorize(...)` explicitly. Filament does not auto-authorize custom actions.
4. Never store applicant documents on a public disk. Always use a private S3-compatible disk. Generate signed, time-limited URLs for previews.
5. Never expose raw numeric IDs in public-facing routes. Use ULIDs or opaque tracking tokens.
6. Use ULIDs for all sensitive primary keys.
7. Wrap all workflow state transitions in database transactions. Status changes, payment success, and decisions must be atomic.
8. Write or update tests with every feature. Run `php artisan test --compact` before declaring a task done.
9. Do not create unrelated refactors in the same task.
10. Do not invent package APIs — use `search-docs` via Laravel Boost to verify API signatures.
11. Run `vendor/bin/pint --dirty --format agent` after any PHP file changes.
12. Decision letters are generated from the immutable submitted snapshot, not from live application data.
13. Webhook handlers must be idempotent. Duplicate events must not create duplicate records.

## Task Prompt Template

```
Implement feature: [feature name]

Context:
- Laravel 12 + Filament 4
- Domain folder: app/Domain/
- Business logic in Actions only — not controllers or Filament resources
- MySQL-compatible migrations (json not jsonb)
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
- Run: php artisan test --compact
- Run: vendor/bin/pint --dirty --format agent
- Explain changed files
- Explain any assumptions made
```

## Security Non-Negotiables

See `docs/security-rules.md` for the full list. Short version:

- Every model: `$fillable` defined, Policy exists
- Every file upload: server-side MIME, extension, size, page validation
- Every document: private storage, ULID path, SHA-256 checksum
- Every status change: database transaction
- Every webhook: idempotency check before processing
- Every Filament custom action: explicit `->authorize(...)`
