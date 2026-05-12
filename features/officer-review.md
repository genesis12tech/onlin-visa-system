# Feature: Officer Review & Decisions (Milestone 5)

## Scope
Filament-based officer portal for case assignment, document review, and final decisions.

## Filament Resource: `VisaApplicationResource`

### Table Filters
- Status, visa type, country, assigned officer, submitted date range

### Table Actions
- Assign to officer
- Request additional information
- Schedule appointment
- Approve application
- Reject application

### Relation Managers
- Documents
- Payments
- Notes
- Status history

## Domain Actions
- `AssignApplicationToOfficer`
- `RequestAdditionalInformation`
- `ScheduleAppointment`
- `ApproveApplication`
- `RejectApplication`

## Acceptance Criteria
- [ ] Officer sees only their assigned applications (unless senior officer or admin policy permits more)
- [ ] Officer cannot approve if required documents are missing, infected, pending, or rejected
- [ ] Every decision records: actor, timestamp, old status, new status, reason
- [ ] Decision letter generated from immutable submitted snapshot
- [ ] Applicant receives email and database notification after every decision or info request

## Security Requirements
- `VisaApplicationPolicy` with officer-scoped visibility
- Every custom Filament action must call `->authorize(...)` explicitly
- Senior officer and admin policies must explicitly grant broader access
- Decisions must be wrapped in database transactions

## Tests Required
- Policy: officer cannot see unassigned applications
- Policy: senior officer can see all applications
- Feature: approve action blocked when documents are not all accepted
- Feature: rejection records full audit trail
- Feature: applicant notification dispatched on decision
