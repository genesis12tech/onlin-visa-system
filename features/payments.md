# Feature: Payments & Invoices (Milestone 4)

## Scope
Stripe Checkout integration, payment ledger, webhook idempotency, invoice generation.

## Models
- `payments` — payment attempt record with provider IDs and status
- `payment_items` — line items (snapshot of fee at payment time)
- `payment_webhook_events` — idempotency table for incoming Stripe events
- `invoices` — invoice record with PDF path

## Actions
- `CalculateApplicationFee` — derives total from visa fee rules
- `CreateCheckoutSession` — creates Stripe Checkout session and payment record
- `HandlePaymentWebhook` — idempotent handler for Stripe webhook events
- `GenerateReceiptPdf` — queued job that produces PDF from payment ledger data

## Design Constraints
- Ledger-oriented — no single `paid` boolean anywhere
- Track payment attempts, provider IDs, webhook events, line items, failure reasons separately
- Receipt PDF uses payment ledger data, not mutable live values
- Webhook handler must be idempotent — duplicate events must not create duplicate records

## Acceptance Criteria
- [ ] Applicant can start a Stripe Checkout session for their application
- [ ] Payment amount calculated from visa fee rules via `CalculateApplicationFee`
- [ ] Duplicate webhook events are idempotent
- [ ] Payment success transitions the application status exactly once
- [ ] Failed payment leaves application in `payment_pending`
- [ ] Receipt PDF dispatched as queued job on payment success
- [ ] Finance role can view payments but cannot alter applications

## Security Requirements
- `PaymentPolicy` required
- Stripe webhook signature verification mandatory
- Payment amounts must be calculated server-side — never trust client-provided totals

## Tests Required
- Unit: `CalculateApplicationFee` returns correct total for fee rules
- Feature: Stripe webhook marks payment succeeded exactly once (idempotency test)
- Feature: duplicate webhook event does not create duplicate payment record
- Policy: applicant cannot view another applicant's payment
