# Stripe Smoke Test Plan

> **This is a manual test plan, not a code implementation plan.** The automated unit/feature tests for payments already pass (22 tests). This plan walks through the full live flow using Stripe test mode and the Stripe CLI webhook forwarder.

**Goal:** Verify the complete payment lifecycle end-to-end — fee summary → Stripe Checkout → webhook → invoice creation → receipt PDF → success page — using real Stripe test-mode API calls.

**Prerequisites:** A visa application in `submitted` status (created via the applicant portal), and real Stripe test keys.

---

## Phase 1: Configure Stripe test keys

### Step 1.1 — Get your test keys

Go to [https://dashboard.stripe.com/test/apikeys](https://dashboard.stripe.com/test/apikeys) (make sure "Test mode" toggle is ON).

Copy:
- **Publishable key** → starts with `pk_test_`
- **Secret key** → starts with `sk_test_`

### Step 1.2 — Update `.env`

```
STRIPE_KEY=pk_test_YOUR_KEY_HERE
STRIPE_SECRET=sk_test_YOUR_KEY_HERE
STRIPE_WEBHOOK_SECRET=    ← leave blank for now; set in Step 2.2
```

Run:
```bash
php artisan config:clear
```

---

## Phase 2: Set up Stripe CLI webhook forwarding

The app runs at `https://visa-application.test` (HTTPS via Herd). Stripe needs to reach your local machine to deliver webhooks.

### Step 2.1 — Install Stripe CLI

```bash
brew install stripe/stripe-cli/stripe
```

Verify:
```bash
stripe --version
```
Expected: `stripe version X.Y.Z`

### Step 2.2 — Log in to Stripe CLI

```bash
stripe login
```
A browser window opens. Approve the connection. Returns to terminal when done.

### Step 2.3 — Start webhook forwarding

In a **separate terminal**, run:

```bash
stripe listen --forward-to https://visa-application.test/webhooks/stripe
```

The CLI prints a webhook signing secret that starts with `whsec_`. Copy it.

### Step 2.4 — Put the webhook secret in `.env`

```
STRIPE_WEBHOOK_SECRET=whsec_YOUR_CLI_SECRET_HERE
```

Run:
```bash
php artisan config:clear
```

> **Keep the `stripe listen` terminal running throughout the smoke test.**

---

## Phase 3: Prepare a test application

You need an application in `submitted` status. Either use an existing one or create one:

1. Log in as an applicant at `https://visa-application.test/login`
   - Email: `applicant@example.com` / password: `password`
2. Start a new application, fill all required fields and documents, and submit it.
3. Note the **tracking number** (e.g. `VA-2026-XXXXXX`).

Confirm the application is in `submitted` status via the admin panel at `https://visa-application.test/admin`.

---

## Phase 4: Run the payment flow

### Step 4.1 — Open the payment page

Visit:
```
https://visa-application.test/applications/VA-2026-XXXXXX/pay
```
(replace with your actual tracking number)

**Expected:** Fee summary page loads showing visa fee line items and a total.

### Step 4.2 — Initiate checkout

Click **"Pay now"** (or the primary payment button).

**Expected:** Browser redirects to `https://checkout.stripe.com/...` — Stripe's hosted checkout page.

Watch the `stripe listen` terminal — you should see:
```
--> payment_intent.created [evt_...]
```

### Step 4.3 — Complete the test payment

On the Stripe Checkout page, use a test card:

| Field | Value |
|---|---|
| Card number | `4242 4242 4242 4242` |
| Expiry | Any future date (e.g. `12/34`) |
| CVC | Any 3 digits (e.g. `123`) |
| Name | Any name |

Click **"Pay"**.

### Step 4.4 — Verify the webhook was delivered

In the `stripe listen` terminal, you should see:

```
<-- [200] POST https://visa-application.test/webhooks/stripe [evt_...]
2026-05-18 HH:MM:SS   --> checkout.session.completed [evt_...]
<-- [200] POST https://visa-application.test/webhooks/stripe [evt_...]
```

Both events must return `200`. A `400` means signature mismatch (wrong `STRIPE_WEBHOOK_SECRET`). A `500` means the webhook handler threw.

### Step 4.5 — Verify the success page

The browser should redirect to:
```
https://visa-application.test/payment/success?session_id=cs_test_...
```

**Expected:**
- Green checkmark, "Payment Successful" heading
- Correct tracking number
- Invoice number in format `INV-2026-000001`
- Amount paid matches the fee summary

---

## Phase 5: Verify backend state

### Step 5.1 — Check application status

In the admin panel (`https://visa-application.test/admin`), find the application.

**Expected status:** `Payment Completed`

### Step 5.2 — Check payment record

In the admin panel → Payments (or via the application detail page).

**Expected:**
- Payment status: `succeeded`
- `provider_payment_intent_id` populated (starts with `pi_test_`)
- `succeeded_at` timestamp set

### Step 5.3 — Check invoice created

**Expected:** An `INV-YYYY-XXXXXX` invoice linked to the payment.

### Step 5.4 — Check receipt PDF job queued

Run Horizon (if not already running):
```bash
php artisan horizon
```

Or check the `pdfs` queue directly:
```bash
php artisan queue:work --queue=pdfs --once
```

**Expected:** `GenerateReceiptPdf` job processes without error.

### Step 5.5 — Check receipt PDF exists

```bash
php artisan tinker --execute '
$invoice = \App\Domain\Payments\Models\Invoice::latest()->first();
echo $invoice->receipt_pdf_path ?? "no pdf path";
'
```

**Expected:** A path like `receipts/INV-2026-000001.pdf`

Verify the file exists on the `documents` disk:
```bash
php artisan tinker --execute '
$invoice = \App\Domain\Payments\Models\Invoice::latest()->first();
echo \Illuminate\Support\Facades\Storage::disk("documents")->exists($invoice->receipt_pdf_path) ? "EXISTS" : "MISSING";
'
```

### Step 5.6 — Check notification sent

```bash
php artisan tinker --execute '
$user = \App\Models\User::where("email", "applicant@example.com")->first();
echo $user->notifications()->latest()->first()?->type ?? "no notification";
'
```

**Expected:** `App\Notifications\PaymentSucceededNotification`

---

## Phase 6: Verify idempotency

Replay the same webhook event to confirm duplicate processing is safe.

In the `stripe listen` terminal output, find the event ID (`evt_...`) from the `checkout.session.completed` event.

Replay it:
```bash
stripe events resend evt_YOUR_EVENT_ID_HERE
```

**Expected:**
- The `stripe listen` terminal shows `<-- [200]`
- No duplicate payment record created
- No duplicate invoice created
- Application status unchanged (`payment_completed`)

---

## Phase 7: Test payment failure path

### Step 7.1 — Start a new application or withdraw and resubmit

Create a fresh `submitted` application (or use the admin panel to reset the status of an existing one for testing).

### Step 7.2 — Open payment page and initiate checkout

Same as Step 4.1–4.2.

### Step 7.3 — Use a declining test card

On the Stripe Checkout page, use:

| Field | Value |
|---|---|
| Card number | `4000 0000 0000 0002` (generic decline) |
| Expiry | Any future date |
| CVC | Any 3 digits |

Click **"Pay"**.

**Expected:**
- Stripe shows "Your card has been declined."
- `payment_intent.payment_failed` event appears in the `stripe listen` terminal with `<-- [200]`
- Application remains in `payment_pending` status
- Payment record has `status = failed` and a `failure_reason`

---

## Checklist Summary

| Check | Expected result | Pass? |
|---|---|---|
| Fee summary page loads | Line items + total visible | |
| Initiating checkout redirects to Stripe | `checkout.stripe.com` URL | |
| Webhook received with 200 | `stripe listen` shows `[200]` | |
| Browser redirects to success page | Invoice number visible | |
| Application status = `payment_completed` | Admin panel confirms | |
| Payment record `status = succeeded` | Payment intent ID populated | |
| Invoice created | `INV-YYYY-XXXXXX` format | |
| Receipt PDF generated | File exists on `documents` disk | |
| Notification sent | `PaymentSucceededNotification` in DB | |
| Duplicate webhook is idempotent | No duplicate records | |
| Declining card stays in `payment_pending` | `failure_reason` set | |

All 11 checks passing = Stripe smoke test complete.
