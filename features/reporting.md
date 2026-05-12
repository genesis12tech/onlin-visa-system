# Feature: Reporting, Exports & Hardening (Milestone 7)

## Scope
Pre-aggregated metrics, queued exports, Filament dashboard widgets, audit log UI, and security hardening.

## Models (pre-aggregated — never query live data for reports)
- `daily_application_metrics`
- `daily_payment_metrics`
- `officer_performance_metrics`
- `document_rejection_metrics`

## Build
- Scheduled jobs to aggregate metrics into summary tables
- Queued CSV/XLSX export jobs written to private storage
- Filament dashboard widgets reading from summary tables
- Audit log review interface in admin panel
- Rate limiting on public and applicant-facing routes
- Security review and load tests

## Acceptance Criteria
- [ ] Reports read from summary tables, not live aggregations
- [ ] Large exports are queued — never synchronous
- [ ] Export files are written to private storage
- [ ] Export access is authorized and audited
- [ ] Sensitive fields are redacted unless a compliance workflow explicitly allows disclosure
- [ ] Failed export jobs are visible in Horizon and retryable

## Security Requirements
- All export access must be authorized via policy
- Export audit trail required
- Sensitive field redaction by default

## Do Not Build Until
The first tourist visa vertical slice (Milestones 1–6) is fully tested and stable.
