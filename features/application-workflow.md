# Feature: Application Workflow (Milestone 2)

## Scope
Dynamic form system, application lifecycle, and status management.

## Models
- `form_templates` — versioned JSON schema per visa type
- `visa_applications` — core application record with status and tracking number
- `application_answers` — per-field answers keyed to form template fields
- `application_snapshots` — immutable snapshot written once at submission
- `application_status_histories` — append-only status audit trail

## Actions
- `CreateDraftApplication` — creates one draft per applicant per visa type
- `UpdateApplicationSection` — saves partial form answers independently
- `SubmitApplication` — validates, locks, writes snapshot, transitions status
- `GenerateTrackingNumber` — produces a non-sequential opaque token

## Acceptance Criteria
- [ ] Applicant can create one draft application per visa type
- [ ] Applicant can save each form section independently
- [ ] Applicant can submit only when all required fields are valid
- [ ] Submitted snapshot is immutable
- [ ] Application receives a non-sequential tracking number
- [ ] Status history is append-only
- [ ] Applicant cannot edit a submitted application unless status explicitly allows corrections

## Security Requirements
- `VisaApplicationPolicy` required
- ULIDs for all application records
- No raw IDs in public-facing routes — use `tracking_number`
- All status transitions wrapped in database transactions

## Tests Required
- Unit: `GenerateTrackingNumber` produces unique, non-sequential tokens
- Feature: applicant can create and submit a draft application
- Feature: submitted snapshot is immutable
- Policy: applicant cannot access another applicant's application
