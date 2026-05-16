# CLAUDE-FRONTEND.md — Visa Application System (Frontend)

> This file governs every AI agent working on the **frontend** of this codebase.
> Read it in full before writing a single line of HTML, CSS, PHP, or JavaScript.
> The backend rules live in `CLAUDE.md` — read both when your work touches both layers.
> Never deviate from these rules without explicit instruction from the project owner.

---

## What this file covers

The frontend is split into four surfaces:

| Surface | Technology | Route prefix |
|---|---|---|
| Applicant portal | Blade + Livewire 3 | `/` |
| Officer panel | Filament 4 (OfficerPanelProvider) | `/officer` |
| Admin panel | Filament 4 (AdminPanelProvider) | `/admin` |
| Public tracking | Plain Blade — no auth | `/track` |

Filament panel rules live in section 6. Livewire rules live in section 4.
Always check which surface you are building for before starting.

---

## 1. Stack (locked — do not substitute)

| Layer | Choice | Notes |
|---|---|---|
| Blade templating | Laravel 12 Blade | All applicant portal views |
| Reactivity | Livewire 3 | No Vue, no React, no Inertia |
| CSS framework | Tailwind CSS 4 | Via Vite — no CDN |
| Officer & admin UI | Filament 4 | Two separate panel providers |
| Icons | Tabler Icons (`@tabler/icons` webfont) | Consistent across all surfaces |
| Fonts | System font stack via Tailwind | No Google Fonts — privacy |
| Dark mode | Tailwind class strategy on `<html>` | Supported from M0 |
| PDF rendering | Dompdf Blade templates | `resources/views/pdfs/` |
| Build tool | Vite | `npm run dev` / `npm run build` |
| Package manager | npm | Do not use Yarn or pnpm |

### What NOT to install
- Do not install Alpine.js — Livewire 3 includes it
- Do not install Vue, React, Svelte, or any JS framework
- Do not install a separate CSS-in-JS or styled-components solution
- Do not install Heroicons, Font Awesome, or Lucide — use Tabler only
- Do not use a CDN for any dependency — everything through npm/Vite

---

## 2. File & folder structure

```
resources/
  views/
    layouts/
      app.blade.php          # Applicant portal shell — header, nav, flash, scripts
      guest.blade.php        # Unauthenticated pages — register, login, track
      pdf.blade.php          # PDF generation shell — no nav, print-safe
    components/              # Shared x-* Blade components (see section 3)
    livewire/                # Livewire component views (mirrors app/Livewire/)
    pages/                   # Non-Livewire Blade pages
      auth/
        register.blade.php
        login.blade.php
        verify-email.blade.php
        mfa-challenge.blade.php
        forgot-password.blade.php
        reset-password.blade.php
      profile/
        setup.blade.php      # Profile wizard shell
      dashboard.blade.php
      track.blade.php        # Public tracking page
    pdfs/
      receipt.blade.php
      decision-letter.blade.php
      appointment-letter.blade.php
      application-summary.blade.php
  css/
    app.css                  # Tailwind imports + custom tokens
  js/
    app.js                   # Livewire + Tabler icon import

app/
  Livewire/                  # All Livewire component classes
    Profile/
      SetupWizard.php
    Applications/
      ApplicationWizard.php
      DynamicFormSection.php
      DocumentUploadPanel.php
      StatusTimeline.php
    Dashboard/
      NotificationBell.php
    Payments/
      PaymentFeeCalculator.php
    Tracking/
      PublicTrackingForm.php
  Http/
    Controllers/             # Thin — call Domain Actions only
    Requests/                # Form Requests for non-Livewire routes
  Filament/
    Admin/
      Resources/
      Pages/
      Widgets/
    Officer/
      Resources/
      Pages/
      Widgets/
```

---

## 3. Blade component library (`x-*`)

These components must exist from Milestone 0. Every page uses them.
Do not inline the same UI pattern twice — always extract to a component.

| Component | File | Purpose |
|---|---|---|
| `x-button` | `components/button.blade.php` | Primary, secondary, danger variants + loading state |
| `x-input` | `components/input.blade.php` | Label, error slot, required indicator, hint slot |
| `x-select` | `components/select.blade.php` | Wraps native select with label + error |
| `x-textarea` | `components/textarea.blade.php` | Resizable, label, error |
| `x-alert` | `components/alert.blade.php` | success / error / warning / info — dismissible |
| `x-badge` | `components/badge.blade.php` | Maps `ApplicationStatus` enum to colour |
| `x-card` | `components/card.blade.php` | Title slot, body slot, optional footer slot |
| `x-step-indicator` | `components/step-indicator.blade.php` | Steps array + current step — wizard progress |
| `x-empty-state` | `components/empty-state.blade.php` | Icon, heading, description, optional CTA slot |
| `x-status-timeline` | `components/status-timeline.blade.php` | Collection of status history records → timeline |
| `x-flash` | `components/flash.blade.php` | Renders session flash messages |
| `x-modal` | `components/modal.blade.php` | Accessible modal with backdrop, title slot, body slot |

### Component rules
- Components are **presentation only** — no database queries, no Domain Action calls
- Props use typed PHP 8 constructor syntax in the component class
- Every component that renders user input must handle `$errors` gracefully
- Every interactive component must have a `aria-label` or visible label — no icon-only buttons without labels

---

## 4. Livewire rules (non-negotiable)

### 4.1  One component, one responsibility
Each Livewire component does exactly one thing.

```
CORRECT
ApplicationWizard   — manages step state and section saves
DynamicFormSection  — renders one form section from JSON schema
DocumentUploadPanel — handles upload, progress, scan status

WRONG
ApplicationWizard that also renders documents AND handles payment
```

### 4.2  Components never call the database directly
Livewire components call Domain Actions, not Eloquent models.

```php
// CORRECT
public function saveSection(string $sectionKey, array $data): void
{
    UpdateApplicationSection::run($this->application, $sectionKey, $data);
}

// WRONG
public function saveSection(string $sectionKey, array $data): void
{
    ApplicationAnswer::updateOrCreate(
        ['application_id' => $this->applicationId, 'section_key' => $sectionKey],
        ['value' => $data]
    );
}
```

### 4.3  Slow work is always queued — never in a component method
```php
// CORRECT
public function uploadDocument(TemporaryUploadedFile $file): void
{
    UploadDocumentVersion::run($this->document, $file);
    // UploadDocumentVersion internally dispatches ScanDocumentJob
}

// WRONG
public function uploadDocument(TemporaryUploadedFile $file): void
{
    Storage::put(...);
    $this->runVirusScan($file); // blocks the request
}
```

### 4.4  Auto-save pattern (section saves)
- Debounce blur events at 800ms using `wire:model.blur` or a `wire:keydown.debounce.800ms`
- Show a "Saved" badge for 2 seconds after each successful save using a `$savedAt` property
- Failed saves show an inline error with a retry button — not a disappearing toast
- Section saves must never change `visa_applications.status`

### 4.5  Polling rules
- Use `wire:poll` only for scan status (document virus check) and export readiness
- Poll interval: 3 seconds maximum
- Stop polling once the terminal state is reached (`clean`, `infected`, `ready`, `failed`)
- Always include a timeout: stop polling after 5 minutes and show a "check back later" message

### 4.6  Form validation
- Validate with `$rules` array in the component and call `$this->validate()` before any action
- Use Form Request classes for non-Livewire POST routes
- Never trust client-side validation alone — the Domain Action must validate too

---

## 5. Tailwind CSS rules

### 5.1  Use design tokens — never hardcode colours
```html
<!-- CORRECT -->
<div class="bg-blue-600 text-white">...</div>

<!-- WRONG — brittle, breaks dark mode, inconsistent -->
<div style="background-color: #185FA5; color: white;">...</div>
```

### 5.2  ApplicationStatus colour token map
This mapping is used by `x-badge` and `x-status-timeline`. Do not deviate.

| Status group | Tailwind colour |
|---|---|
| `draft` | `gray` |
| `submitted`, `payment_pending` | `blue` |
| `paid`, `under_review`, `document_verification` | `purple` |
| `info_requested`, `resubmitted` | `amber` |
| `interview_scheduled` | `blue` |
| `approved` | `green` |
| `rejected`, `withdrawn`, `closed` | `red` / `gray` |

### 5.3  Dark mode
- All views must respect dark mode from M0
- Use Tailwind's `dark:` variant — never hardcode light-only colours
- Test every new component in both light and dark mode before marking complete

### 5.4  Responsive design
- Applicant portal is mobile-first — minimum viewport 380px
- Two-column layouts collapse to single column below `md` (768px)
- Wizard step nav scrolls horizontally on mobile — never wraps
- Tables scroll horizontally — never truncate data on small screens

### 5.5  No custom CSS unless unavoidable
Write Tailwind utilities. Only add `app.css` classes for things Tailwind cannot express.
Every custom CSS class must have a comment explaining why Tailwind was insufficient.

---

## 6. Filament panel rules

### 6.1  Two panels — never mix them
```
AdminPanelProvider   → app/Providers/Filament/AdminPanelProvider.php
OfficerPanelProvider → app/Providers/Filament/OfficerPanelProvider.php
```

A resource registered in one panel must not appear in the other without an explicit decision.

### 6.2  Every custom action must call `->authorize()`
Filament does NOT auto-authorize custom actions.

```php
// CORRECT
Action::make('approve')
    ->authorize(fn (VisaApplication $record) => auth()->user()->can('approve', $record))
    ->action(fn (VisaApplication $record) => ApproveApplication::run($record, ...));

// WRONG — missing authorize, any officer can approve any application
Action::make('approve')
    ->action(fn (VisaApplication $record) => ApproveApplication::run($record, ...));
```

### 6.3  Resources call Domain Actions — no Eloquent in resource classes
```php
// CORRECT — resource action calls a Domain Action
->action(fn ($record, $data) => RequestAdditionalInformation::run($record, $data))

// WRONG — Eloquent in resource class
->action(function ($record, $data) {
    $record->update(['status' => 'info_requested']);
    ReviewNote::create([...]);
})
```

### 6.4  Dashboard widgets read from read models only
```php
// CORRECT — reads from daily_application_metrics
protected function getStats(): array
{
    return [
        Stat::make('Submitted today', DailyApplicationMetrics::today()->sum('submitted_count')),
    ];
}

// WRONG — live COUNT() against transactional table
protected function getStats(): array
{
    return [
        Stat::make('Submitted today', VisaApplication::whereDate('submitted_at', today())->count()),
    ];
}
```

### 6.5  Document serving — never direct S3 URLs in Filament
```php
// CORRECT — signed temporary URL through controller
Action::make('view')
    ->url(fn ($record) => route('officer.documents.preview', $record))

// WRONG — direct S3 URL bypasses policy and audit log
Action::make('view')
    ->url(fn ($record) => Storage::disk('private')->url($record->storage_path))
```

### 6.6  Officer scope is enforced at policy level — not just filters
The `VisaApplicationPolicy::viewAny()` method applies the scope.
Officers must not be able to remove the "assigned to me" filter via URL manipulation.

---

## 7. PDF templates (Dompdf)

All PDF Blade templates live in `resources/views/pdfs/`.

### 7.1  Templates receive a DTO — never query the database
```php
// CORRECT — GenerateReceiptPdfJob assembles DTO and passes it
$html = view('pdfs.receipt', ['receipt' => $receiptData])->render();

// WRONG — template queries database mid-render
// receipt.blade.php: {{ $invoice->payment->items->sum('amount') }}
```

### 7.2  Always use the `pdf.blade.php` layout
```blade
@extends('layouts.pdf')

@section('content')
    {{-- receipt content --}}
@endsection
```

### 7.3  Immutable data only
- Decision letter: data from `application_snapshots` + `visa_applications.metadata` only
- Receipt: data from `invoices` + `payment_items` only
- Never render live `applicant_profiles` data in a PDF — use the snapshot

### 7.4  Dompdf constraints
- Use inline styles for critical layout — Dompdf does not support all CSS
- No `position: fixed` or `position: sticky` — these break Dompdf pagination
- No Flexbox or CSS Grid — use `display: table` for multi-column layouts in PDFs
- Test every PDF template by actually generating it — do not assume it renders correctly

---

## 8. Security rules (frontend)

| # | Rule |
|---|---|
| 1 | Never expose a ULID or database ID in a public URL — use `tracking_number` or route model binding with ULIDs scoped by policy |
| 2 | Never render sensitive fields (passport number, DOB) without the user being authenticated and the correct policy passing |
| 3 | Passport numbers shown in the applicant portal must be masked (last 4 digits only) — full number only in PDF output |
| 4 | Public tracking page must not reveal whether a tracking number exists if verification fails — same error message for both cases |
| 5 | Every file download must go through a controller that checks policy and writes to `audit_logs` |
| 6 | CSRF tokens are required on all non-GET routes — do not exclude any route except verified webhook endpoints |
| 7 | Rate limit: public tracking 10/hour per IP, OTP send 3/15min per address, login 5/min per IP |
| 8 | Never render officer notes, internal status sub-states, or document counts on the public tracking page |
| 9 | MFA challenge page must not leak which account the login attempt is for |
| 10 | All Livewire component actions that mutate data must re-check authorization server-side — never trust `wire:click` alone |

---

## 9. The shared `x-status-timeline` component

This component is used in three places. It must work identically in all three.

| Context | Status source | Labels |
|---|---|---|
| Applicant dashboard card | `application_status_histories` filtered to public_label != null | Public labels only |
| Application detail page (authenticated) | All `application_status_histories` records | Full internal labels |
| Public tracking result | Same as dashboard card | Public labels only |

```blade
{{-- Usage --}}
<x-status-timeline
    :histories="$application->statusHistories"
    :public-only="$publicOnly"
/>
```

The component accepts a `$publicOnly` boolean. When `true`, it filters out any history record where `public_label` is null (internal-only transitions).

---

## 10. Dynamic form renderer (ApplicationWizard step 3)

The form schema is JSON stored in `form_templates.schema`. The renderer must:

1. Read `sections` array from the schema
2. For each section, render its `fields` array
3. For each field, render the correct input type based on `field_type` (text, date, select, textarea, radio, checkbox)
4. Apply `condition_rules` — show/hide fields based on sibling field values
5. Save section data to `application_answers` via `UpdateApplicationSection` on blur
6. Never hardcode fields for a specific visa type

```json
// Example schema field shape
{
    "key": "employer_name",
    "type": "text",
    "label": "Employer name",
    "required": true,
    "condition": {
        "field": "occupation",
        "equals": "employed"
    }
}
```

The Livewire component holds `$answers` as an associative array keyed by `section_key.field_key`.
Conditional visibility is computed in a `computed()` property — not in the Blade template.

---

## 11. ApplicationWizard state machine

```
draft (application created)
  │
  ├── step 1: visa type selected
  ├── step 2: travel details saved
  ├── step 3: personal info saved
  ├── step 4: documents uploaded (all required = accepted or scanning)
  ├── step 5: review — declaration checked
  │
  └── SubmitApplication called → status = submitted → redirect to Stripe
```

**The "Submit & pay" button is disabled until:**
- All required documents are in `accepted` or `scanning` state (not `pending`, `rejected`, `infected`)
- The declaration checkbox is checked
- All required fields in all sections pass validation

The Livewire component checks these conditions in a `canSubmit()` computed property.
The `SubmitApplication` action enforces all conditions server-side independently.

---

## 12. After every frontend change

Run these before marking a task complete:

```bash
npm run build              # must succeed with zero errors
php artisan test           # must pass
php artisan pint --test    # must pass (zero style violations)
```

Then confirm:
1. The page renders correctly in both light and dark mode
2. The page is usable at 380px viewport width (mobile)
3. No database queries run inside Blade templates or Livewire component `render()` methods
4. No raw IDs appear in URLs, hidden inputs, or rendered HTML
5. Every new component has at least one Pest test covering its public interface

---

## 13. What NOT to build (post-MVP only)

Do not implement these until the M0–M4 vertical slice is working end-to-end:

- Drag-and-drop form builder UI for admins
- Real-time collaborative editing
- In-browser document scanning or OCR
- Push notifications (web push / PWA)
- Offline mode or service worker caching
- Applicant mobile app
- Multi-language / i18n UI (scaffold the structure, don't translate)
- SMS notification preferences UI
- Custom Filament theme beyond the two panel colours
- Chart.js or D3 dashboards — use Filament's built-in stats widgets for M0–M4

---

## 14. Milestone checklist (frontend)

| # | Milestone | Deliverables | Done? |
|---|---|---|---|
| 0 | Foundation | Tailwind 4 + Vite, Livewire 3, auth layouts, component library (all x-* components), dark mode, `npm run build` passes | ☐ |
| 1 | Registration | Register, login, MFA challenge, email verify, profile wizard (personal + passport steps), dashboard shell (empty state) | ☐ |
| 2 | Application wizard | Visa type picker, dynamic form renderer, travel/personal/employment sections, auto-save, progress bar, review step, tracking number display | ☐ |
| 3 | Documents | Upload panel, drag-and-drop, progress ring, scan status polling, rejection display, resubmit flow, document checklist | ☐ |
| 4 | Payment | Fee summary page, priority toggle, Stripe redirect, payment success page, receipt download, public tracking page | ☐ |
| 5 | Officer UI | Officer queue table, application detail split-pane, document lightbox, approve/reject/request-info/appt modals, notes panel | ☐ |
| 6 | Notifications | Notification bell + dropdown, lifecycle email templates, all PDF Blade templates (receipt, decision letter, summary, appointment letter) | ☐ |
| 7 | Reports + hardening | KPI widgets, application/payment/officer/doc-rejection dashboards, export queue UI, rate limit feedback pages, browser tests | ☐ |

---

## 15. Key references

| Topic | URL |
|---|---|
| Livewire 3 docs | https://livewire.laravel.com |
| Tailwind CSS 4 | https://tailwindcss.com/docs |
| Filament 4 docs | https://filamentphp.com/docs |
| Tabler Icons | https://tabler.io/icons |
| Dompdf package | https://github.com/barryvdh/laravel-dompdf |
| Vite + Laravel | https://laravel.com/docs/12.x/vite |
| Laravel Blade components | https://laravel.com/docs/12.x/blade#components |
| Livewire file uploads | https://livewire.laravel.com/docs/uploads |
| Livewire polling | https://livewire.laravel.com/docs/polling |
