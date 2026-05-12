# Security Rules

Non-negotiable security requirements for all code in this project.

## Authorization

1. Every sensitive model must have a Policy registered in `AuthServiceProvider` (or auto-discovered).
2. Every custom Filament action must call `$this->authorize(...)` or `->authorize(...)` explicitly. Filament does not auto-authorize custom actions.
3. Controllers must authorize via Policy or Gate — never skip authorization.

## Document Storage

4. Never store applicant documents on a public disk. Always use a private S3-compatible disk.
5. Generate signed, time-limited URLs for document previews — never permanent public URLs.
6. Store original filenames as metadata only. Use ULIDs for actual object storage paths.
7. Block document preview and download until scan status is `clean` (or an explicit, audited exception is granted).

## Data Exposure

8. Never expose raw numeric IDs in public-facing routes. Use ULIDs or opaque tracking tokens.
9. Encrypt sensitive fields at rest: passport numbers, phone numbers, and any government-issued identifiers.

## Workflow Integrity

10. Wrap all workflow state transitions in database transactions. Status changes, payment success, and decisions must be atomic.
11. Webhook idempotency is required. `HandlePaymentWebhook` must be idempotent — duplicate events must not create duplicate records.
12. Decision letters are generated from the immutable submitted snapshot, not from live application data.

## File Upload Validation

13. Server-side document validation is mandatory: MIME type, file extension, file size, and page/image limits.
14. Do not rely solely on Filament's `FileUpload` component for validation — it can be bypassed.

## Mass Assignment

15. Every model must define `$fillable` or `$guarded`. Never use `$guarded = []` on sensitive models.

## Input / Output

16. Use `{{ }}` (not `{!! !!}`) for all user-generated output in Blade — never skip escaping.
17. Never construct raw SQL with user input — use Eloquent query builder bindings.

## Audit

18. Every document action (upload, preview, download, accept, reject) must write an audit log entry.
19. Every application status transition must write an `application_status_histories` row with actor, timestamp, old status, new status, and reason.
