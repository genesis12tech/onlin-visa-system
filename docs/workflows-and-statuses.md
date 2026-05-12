# Workflows and Statuses

## Application Status Lifecycle

```
draft
  └─▶ submitted (on SubmitApplication action)
        └─▶ payment_pending (on payment initiation)
              ├─▶ payment_completed (on Stripe webhook: payment_intent.succeeded)
              │     └─▶ under_review (on officer assignment)
              │           ├─▶ additional_info_requested (officer requests more info)
              │           │     └─▶ under_review (applicant responds)
              │           ├─▶ approved
              │           └─▶ rejected
              └─▶ payment_pending (stays; on Stripe webhook: payment_intent.payment_failed)
  └─▶ withdrawn (applicant withdraws at any pre-decision stage)
```

### Status Definitions

| Status | Who sets it | Description |
|---|---|---|
| `draft` | System | Created, not yet submitted |
| `submitted` | `SubmitApplication` action | All fields valid, snapshot created |
| `payment_pending` | `CreateCheckoutSession` action | Awaiting Stripe payment |
| `payment_completed` | `HandlePaymentWebhook` action | Payment confirmed via webhook |
| `under_review` | `AssignApplicationToOfficer` action | Assigned to an officer |
| `additional_info_requested` | `RequestAdditionalInformation` action | Officer needs more from applicant |
| `approved` | `ApproveApplication` action | Final positive decision |
| `rejected` | `RejectApplication` action | Final negative decision |
| `withdrawn` | Applicant action | Applicant voluntarily withdraws |

### Rules

- All transitions must be wrapped in a database transaction.
- Every transition writes an `application_status_histories` row (append-only).
- An application may only move forward through statuses — backwards movement requires an explicit, audited override.
- Officers cannot approve if any required document is `pending`, `infected`, or `rejected`.

---

## Document Status Lifecycle

```
pending
  └─▶ uploaded (on successful UploadDocumentVersion)
        └─▶ pending_scan (after upload, before virus scan)
              ├─▶ clean → under_review (scan passed)
              │     ├─▶ accepted (officer accepts)
              │     └─▶ rejected (officer rejects)
              │           └─▶ uploaded (applicant replaces — new document_versions row)
              └─▶ infected (scan failed — download/preview blocked permanently)
```

### Document Status Definitions

| Status | Description |
|---|---|
| `pending` | Slot created but no file uploaded yet |
| `uploaded` | File received, awaiting scan |
| `pending_scan` | Sent to virus scanner, awaiting result |
| `under_review` | Scan clean, officer reviewing |
| `accepted` | Officer accepted |
| `rejected` | Officer rejected — applicant can re-upload |
| `infected` | Virus scan failed — permanently blocked |

---

## Payment Status Lifecycle

```
pending
  └─▶ processing (Checkout session created)
        ├─▶ succeeded (webhook: payment_intent.succeeded)
        └─▶ failed (webhook: payment_intent.payment_failed)
              └─▶ processing (applicant retries)

succeeded
  └─▶ refunded (manual finance action)
  └─▶ partially_refunded (partial refund)
```

### Payment Status Definitions

| Status | Description |
|---|---|
| `pending` | Payment record created, no attempt yet |
| `processing` | Checkout session active |
| `succeeded` | Payment confirmed |
| `failed` | Payment attempt failed |
| `refunded` | Full refund issued |
| `partially_refunded` | Partial refund issued |

---

## Notification Triggers

| Event | Channels | Template |
|---|---|---|
| Application submitted | Email + database | Confirmation with tracking number |
| Payment succeeded | Email + database | Receipt with PDF attached |
| Document rejected | Email + database | Which doc, rejection reason |
| Additional info requested | Email + database | What is needed and deadline |
| Application approved | Email + database | Decision letter PDF |
| Application rejected | Email + database | Decision letter PDF |
