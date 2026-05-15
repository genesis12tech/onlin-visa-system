# Milestone 4 — Payments & Invoices Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Stripe Checkout payment flow, idempotent webhook handling, a ledger-oriented payment model layer, invoice creation, and a read-only PaymentResource in Filament.

**Architecture:** Raw `stripe/stripe-php` SDK (no Cashier) because this is a one-time fee, not a subscription. Payments are ledger-oriented: `Payment` → `PaymentItem[]` → `Invoice`. Idempotency is enforced by a unique constraint on `payment_webhook_events.event_id`. PDF receipts are generated in a `GenerateReceiptPdf` queued job dispatched on payment success. The `StripeClient` is bound in `AppServiceProvider` as a singleton so it can be swapped in tests.

**Tech Stack:** Laravel 12, Filament 4, `stripe/stripe-php`, `barryvdh/laravel-dompdf`, PHPUnit

---

## File Map

### New files
- `app/Domain/Payments/Enums/PaymentStatus.php`
- `app/Domain/Payments/Models/Payment.php`
- `app/Domain/Payments/Models/PaymentItem.php`
- `app/Domain/Payments/Models/PaymentWebhookEvent.php`
- `app/Domain/Payments/Models/Invoice.php`
- `app/Domain/Payments/Policies/PaymentPolicy.php`
- `app/Domain/Payments/Actions/CalculateApplicationFee.php`
- `app/Domain/Payments/Actions/CreateCheckoutSession.php`
- `app/Domain/Payments/Actions/HandlePaymentWebhook.php`
- `app/Domain/Payments/Jobs/GenerateReceiptPdf.php`
- `app/Http/Controllers/StripeWebhookController.php`
- `app/Filament/Resources/Payments/PaymentResource.php`
- `app/Filament/Resources/Payments/Pages/ListPayments.php`
- `app/Filament/Resources/Payments/Pages/ViewPayment.php`
- `app/Filament/Resources/Payments/Tables/PaymentsTable.php`
- `app/Filament/Resources/Payments/Infolists/PaymentInfolist.php`
- `app/Filament/Resources/VisaApplications/RelationManagers/PaymentsRelationManager.php`
- `database/factories/PaymentFactory.php`
- `database/factories/InvoiceFactory.php`
- `resources/views/pdfs/receipt.blade.php`
- `tests/Unit/PaymentStatusEnumTest.php`
- `tests/Unit/CalculateApplicationFeeTest.php`
- `tests/Unit/PaymentPolicyTest.php`
- `tests/Feature/CreateCheckoutSessionTest.php`
- `tests/Feature/HandlePaymentWebhookTest.php`

### Modified files
- `app/Domain/Applications/Enums/ApplicationStatus.php` — add PaymentPending, PaymentCompleted, AdditionalInfoRequested, Withdrawn
- `app/Domain/Applications/Models/VisaApplication.php` — add `payments()` HasMany relation
- `app/Providers/AppServiceProvider.php` — bind `StripeClient` singleton, register `PaymentPolicy`
- `bootstrap/app.php` — exempt `/webhooks/*` from CSRF
- `routes/web.php` — add Stripe webhook POST route
- `config/services.php` — add stripe config block
- `.env` / `.env.example` — add `STRIPE_*` vars
- `app/Filament/Resources/VisaApplications/VisaApplicationResource.php` — add `PaymentsRelationManager`

---

## Task 1: Install Dependencies & Configuration

**Files:**
- Modify: `composer.json` (via composer)
- Modify: `config/services.php`
- Modify: `.env`, `.env.example`

- [ ] **Step 1: Install packages**

```bash
cd /Users/thomas/Herd/visa-application && composer require stripe/stripe-php barryvdh/laravel-dompdf
```

- [ ] **Step 2: Publish Dompdf config**

```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider" --no-interaction
```

- [ ] **Step 3: Add Stripe config block to `config/services.php`**

Add before the closing `];`:

```php
    'stripe' => [
        'key'             => env('STRIPE_KEY'),
        'secret'          => env('STRIPE_SECRET'),
        'webhook_secret'  => env('STRIPE_WEBHOOK_SECRET'),
    ],
```

- [ ] **Step 4: Add Stripe vars to `.env` and `.env.example`**

In `.env`:
```
STRIPE_KEY=pk_test_replace_me
STRIPE_SECRET=sk_test_replace_me
STRIPE_WEBHOOK_SECRET=whsec_replace_me
```

In `.env.example`:
```
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
```

- [ ] **Step 5: Verify `php artisan test` still passes**

```bash
php artisan test --compact
```

Expected: all existing tests pass.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock config/services.php .env.example
git commit -m "feat: install stripe/stripe-php and barryvdh/laravel-dompdf"
```

---

## Task 2: Update ApplicationStatus + Add PaymentStatus Enum

**Files:**
- Modify: `app/Domain/Applications/Enums/ApplicationStatus.php`
- Create: `app/Domain/Payments/Enums/PaymentStatus.php`
- Create: `tests/Unit/PaymentStatusEnumTest.php`

- [ ] **Step 1: Write a failing test**

```bash
php artisan make:test --phpunit --unit PaymentStatusEnumTest --no-interaction
```

Replace `tests/Unit/PaymentStatusEnumTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use Tests\TestCase;

class PaymentStatusEnumTest extends TestCase
{
    public function test_application_status_has_payment_pending(): void
    {
        $this->assertEquals('payment_pending', ApplicationStatus::PaymentPending->value);
    }

    public function test_application_status_has_payment_completed(): void
    {
        $this->assertEquals('payment_completed', ApplicationStatus::PaymentCompleted->value);
    }

    public function test_payment_status_has_expected_cases(): void
    {
        $this->assertEquals('pending', PaymentStatus::Pending->value);
        $this->assertEquals('processing', PaymentStatus::Processing->value);
        $this->assertEquals('succeeded', PaymentStatus::Succeeded->value);
        $this->assertEquals('failed', PaymentStatus::Failed->value);
        $this->assertEquals('refunded', PaymentStatus::Refunded->value);
        $this->assertEquals('partially_refunded', PaymentStatus::PartiallyRefunded->value);
    }
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php artisan test --compact --filter=PaymentStatusEnumTest
```

Expected: FAIL — enum cases don't exist yet.

- [ ] **Step 3: Update `ApplicationStatus` enum**

Replace `app/Domain/Applications/Enums/ApplicationStatus.php`:

```php
<?php

namespace App\Domain\Applications\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case PaymentPending = 'payment_pending';
    case PaymentCompleted = 'payment_completed';
    case UnderReview = 'under_review';
    case AdditionalInfoRequested = 'additional_info_requested';
    case DocsRequired = 'docs_required';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft                   => 'Draft',
            self::Submitted               => 'Submitted',
            self::PaymentPending          => 'Payment Pending',
            self::PaymentCompleted        => 'Payment Completed',
            self::UnderReview             => 'In Review',
            self::AdditionalInfoRequested => 'Info Requested',
            self::DocsRequired            => 'Docs Required',
            self::Approved                => 'Approved',
            self::Rejected                => 'Rejected',
            self::Withdrawn               => 'Withdrawn',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft, self::Withdrawn  => 'gray',
            self::Submitted               => 'info',
            self::PaymentPending          => 'warning',
            self::PaymentCompleted        => 'success',
            self::UnderReview             => 'warning',
            self::AdditionalInfoRequested => 'primary',
            self::DocsRequired            => 'primary',
            self::Approved                => 'success',
            self::Rejected                => 'danger',
        };
    }

    public function badgeColor(): string
    {
        return $this->color();
    }
}
```

- [ ] **Step 4: Create `PaymentStatus` enum**

Create `app/Domain/Payments/Enums/PaymentStatus.php`:

```php
<?php

namespace App\Domain\Payments\Enums;

enum PaymentStatus: string
{
    case Pending           = 'pending';
    case Processing        = 'processing';
    case Succeeded         = 'succeeded';
    case Failed            = 'failed';
    case Refunded          = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending           => 'Pending',
            self::Processing        => 'Processing',
            self::Succeeded         => 'Succeeded',
            self::Failed            => 'Failed',
            self::Refunded          => 'Refunded',
            self::PartiallyRefunded => 'Partially Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::Processing             => 'warning',
            self::Succeeded                             => 'success',
            self::Failed, self::Refunded,
            self::PartiallyRefunded                     => 'danger',
        };
    }
}
```

- [ ] **Step 5: Run test — expect PASS**

```bash
php artisan test --compact --filter=PaymentStatusEnumTest
```

Expected: PASS

- [ ] **Step 6: Run full suite to check for regressions**

```bash
php artisan test --compact
```

Expected: all tests pass.

- [ ] **Step 7: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Applications/Enums/ApplicationStatus.php app/Domain/Payments/Enums/PaymentStatus.php tests/Unit/PaymentStatusEnumTest.php
git commit -m "feat: add PaymentPending/PaymentCompleted to ApplicationStatus, add PaymentStatus enum"
```

---

## Task 3: Payment Domain Models + Factories

**Files:**
- Create: `app/Domain/Payments/Models/Payment.php`
- Create: `app/Domain/Payments/Models/PaymentItem.php`
- Create: `app/Domain/Payments/Models/PaymentWebhookEvent.php`
- Create: `app/Domain/Payments/Models/Invoice.php`
- Modify: `app/Domain/Applications/Models/VisaApplication.php`
- Create: `database/factories/PaymentFactory.php`
- Create: `database/factories/InvoiceFactory.php`

The migrations for all four tables already exist (created in the initial scaffold). This task adds the Eloquent models and factories only.

- [ ] **Step 1: Create `Payment` model**

Create `app/Domain/Payments/Models/Payment.php`:

```php
<?php

namespace App\Domain\Payments\Models;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Payments\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'visa_application_id',
        'status',
        'provider',
        'provider_payment_intent_id',
        'provider_checkout_session_id',
        'amount_subtotal',
        'amount_total',
        'currency',
        'failure_reason',
        'succeeded_at',
    ];

    protected function casts(): array
    {
        return [
            'status'         => PaymentStatus::class,
            'amount_subtotal' => 'integer',
            'amount_total'   => 'integer',
            'succeeded_at'   => 'datetime',
        ];
    }

    public function visaApplication(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'visa_application_id', 'ulid');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PaymentItem::class, 'payment_id', 'ulid');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'payment_id', 'ulid');
    }
}
```

- [ ] **Step 2: Create `PaymentItem` model**

Create `app/Domain/Payments/Models/PaymentItem.php`:

```php
<?php

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentItem extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'payment_id',
        'visa_fee_id',
        'description',
        'quantity',
        'unit_amount',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity'     => 'integer',
            'unit_amount'  => 'integer',
            'total_amount' => 'integer',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'ulid');
    }
}
```

- [ ] **Step 3: Create `PaymentWebhookEvent` model**

Create `app/Domain/Payments/Models/PaymentWebhookEvent.php`:

```php
<?php

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'payload',
        'processed_at',
        'processing_error',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'processed_at' => 'datetime',
            'created_at'   => 'datetime',
        ];
    }

    public function markProcessed(): void
    {
        $this->update(['processed_at' => now()]);
    }

    public function markFailed(string $error): void
    {
        $this->update(['processing_error' => $error]);
    }
}
```

- [ ] **Step 4: Create `Invoice` model**

Create `app/Domain/Payments/Models/Invoice.php`:

```php
<?php

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasUlids;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'payment_id',
        'invoice_number',
        'issued_at',
        'pdf_storage_path',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'ulid');
    }
}
```

- [ ] **Step 5: Add `payments()` relation to `VisaApplication`**

In `app/Domain/Applications/Models/VisaApplication.php`:

Add import at the top (with the other use statements):
```php
use App\Domain\Payments\Models\Payment;
```

Add method at the bottom of the class (before the closing `}`):
```php
public function payments(): HasMany
{
    return $this->hasMany(Payment::class, 'visa_application_id', 'ulid');
}
```

- [ ] **Step 6: Create `PaymentFactory`**

Create `database/factories/PaymentFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'visa_application_id'          => fake()->ulid(),
            'status'                       => PaymentStatus::Pending,
            'provider'                     => 'stripe',
            'provider_payment_intent_id'   => null,
            'provider_checkout_session_id' => null,
            'amount_subtotal'              => 10000,
            'amount_total'                 => 10000,
            'currency'                     => 'USD',
            'failure_reason'               => null,
            'succeeded_at'                 => null,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn () => [
            'status'                       => PaymentStatus::Succeeded,
            'provider_payment_intent_id'   => 'pi_test_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'provider_checkout_session_id' => 'cs_test_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'succeeded_at'                 => now(),
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status'                       => PaymentStatus::Processing,
            'provider_checkout_session_id' => 'cs_test_'.fake()->regexify('[A-Za-z0-9]{24}'),
        ]);
    }
}
```

- [ ] **Step 7: Create `InvoiceFactory`**

Create `database/factories/InvoiceFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Domain\Payments\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'payment_id'       => fake()->ulid(),
            'invoice_number'   => 'INV-'.date('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'issued_at'        => now(),
            'pdf_storage_path' => null,
        ];
    }
}
```

- [ ] **Step 8: Run full test suite**

```bash
php artisan test --compact
```

Expected: all tests pass.

- [ ] **Step 9: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Payments/Models/ app/Domain/Applications/Models/VisaApplication.php database/factories/PaymentFactory.php database/factories/InvoiceFactory.php
git commit -m "feat: add Payment, PaymentItem, PaymentWebhookEvent, Invoice models and factories"
```

---

## Task 4: Make `actor_id` Nullable on `application_status_histories`

**Context:** Stripe webhook-triggered status transitions (e.g. `payment_pending` → `payment_completed`) have no human actor. The existing `actor_id` column is non-nullable with a FK constraint, which would fail on webhook-driven inserts. This migration makes it nullable, which is the correct modelling choice for system-initiated transitions.

**Files:**
- Create: new migration via artisan

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration make_actor_id_nullable_on_application_status_histories --no-interaction
```

Replace the generated migration file content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_status_histories', function (Blueprint $table): void {
            $table->foreignId('actor_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('application_status_histories', function (Blueprint $table): void {
            $table->foreignId('actor_id')->nullable(false)->change();
        });
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate --no-interaction
```

- [ ] **Step 3: Update `ApplicationStatusHistory` model to allow null actor_id**

Open `app/Domain/Applications/Models/ApplicationStatusHistory.php`. In the `$fillable` array, confirm `actor_id` is present. In the `casts()` method (or property), no change needed since it's an integer FK.

If the model does not yet exist, create it at `app/Domain/Applications/Models/ApplicationStatusHistory.php`:

```php
<?php

namespace App\Domain\Applications\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationStatusHistory extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $primaryKey = 'ulid';

    protected $fillable = [
        'visa_application_id',
        'from_status',
        'to_status',
        'actor_id',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function visaApplication(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'visa_application_id', 'ulid');
    }
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --compact
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/ app/Domain/Applications/Models/ApplicationStatusHistory.php
git commit -m "feat: make actor_id nullable on application_status_histories for system-triggered transitions"
```

---

## Task 5: PaymentPolicy

**Files:**
- Create: `app/Domain/Payments/Policies/PaymentPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `tests/Unit/PaymentPolicyTest.php`

- [ ] **Step 1: Write failing test**

```bash
php artisan make:test --phpunit --unit PaymentPolicyTest --no-interaction
```

Replace `tests/Unit/PaymentPolicyTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Policies\PaymentPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PaymentPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['finance_officer', 'admin', 'super_admin', 'applicant'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_finance_officer_can_view_any_payment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('finance_officer');

        $this->assertTrue((new PaymentPolicy)->viewAny($user));
    }

    public function test_admin_can_view_any_payment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->assertTrue((new PaymentPolicy)->viewAny($user));
    }

    public function test_applicant_cannot_view_any_payment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('applicant');

        $this->assertFalse((new PaymentPolicy)->viewAny($user));
    }

    public function test_finance_officer_cannot_create_payment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('finance_officer');

        $this->assertFalse((new PaymentPolicy)->create($user));
    }

    public function test_finance_officer_cannot_update_payment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('finance_officer');

        $this->assertFalse((new PaymentPolicy)->update($user, new Payment));
    }

    public function test_applicant_cannot_view_a_payment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('applicant');

        $this->assertFalse((new PaymentPolicy)->view($user, new Payment));
    }
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php artisan test --compact --filter=PaymentPolicyTest
```

Expected: FAIL — class not found.

- [ ] **Step 3: Create `PaymentPolicy`**

Create `app/Domain/Payments/Policies/PaymentPolicy.php`:

```php
<?php

namespace App\Domain\Payments\Policies;

use App\Domain\Payments\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'finance_officer']);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'finance_officer']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }
}
```

- [ ] **Step 4: Register policy and `StripeClient` singleton in `AppServiceProvider`**

In `app/Providers/AppServiceProvider.php`:

Add imports:
```php
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Policies\PaymentPolicy;
use Stripe\StripeClient;
```

In `register()`:
```php
$this->app->singleton(StripeClient::class, fn () => new StripeClient(config('services.stripe.secret')));
```

In `boot()`, add:
```php
Gate::policy(Payment::class, PaymentPolicy::class);
```

- [ ] **Step 5: Run test — expect PASS**

```bash
php artisan test --compact --filter=PaymentPolicyTest
```

Expected: PASS

- [ ] **Step 6: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Payments/Policies/PaymentPolicy.php app/Providers/AppServiceProvider.php tests/Unit/PaymentPolicyTest.php
git commit -m "feat: add PaymentPolicy and StripeClient singleton binding"
```

---

## Task 6: `CalculateApplicationFee` Action

**Files:**
- Create: `app/Domain/Payments/Actions/CalculateApplicationFee.php`
- Create: `tests/Unit/CalculateApplicationFeeTest.php`

Returns `['items' => Collection, 'total_amount' => int (cents), 'currency' => string]`. Throws `RuntimeException` if no active fees are found.

- [ ] **Step 1: Write failing tests**

```bash
php artisan make:test --phpunit --unit CalculateApplicationFeeTest --no-interaction
```

Replace `tests/Unit/CalculateApplicationFeeTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Actions\CalculateApplicationFee;
use App\Domain\Payments\Models\VisaFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateApplicationFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sums_all_active_current_fees(): void
    {
        $visaType = $this->makeVisaType();

        VisaFee::create([
            'visa_type_id'   => $visaType->ulid,
            'name'           => 'Application Fee',
            'amount'         => 5000,
            'currency'       => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active'      => true,
        ]);

        VisaFee::create([
            'visa_type_id'   => $visaType->ulid,
            'name'           => 'Processing Fee',
            'amount'         => 2000,
            'currency'       => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active'      => true,
        ]);

        $result = (new CalculateApplicationFee)->execute($this->makeApplication($visaType));

        $this->assertEquals(7000, $result['total_amount']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertCount(2, $result['items']);
    }

    public function test_excludes_inactive_fees(): void
    {
        $visaType = $this->makeVisaType();

        VisaFee::create([
            'visa_type_id'   => $visaType->ulid,
            'name'           => 'Active Fee',
            'amount'         => 5000,
            'currency'       => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active'      => true,
        ]);

        VisaFee::create([
            'visa_type_id'   => $visaType->ulid,
            'name'           => 'Inactive Fee',
            'amount'         => 3000,
            'currency'       => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active'      => false,
        ]);

        $result = (new CalculateApplicationFee)->execute($this->makeApplication($visaType));

        $this->assertEquals(5000, $result['total_amount']);
        $this->assertCount(1, $result['items']);
    }

    public function test_excludes_expired_fees(): void
    {
        $visaType = $this->makeVisaType();

        VisaFee::create([
            'visa_type_id'   => $visaType->ulid,
            'name'           => 'Expired Fee',
            'amount'         => 5000,
            'currency'       => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subMonth(),
            'effective_to'   => now()->subDay(),
            'is_active'      => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No active fees found');

        (new CalculateApplicationFee)->execute($this->makeApplication($visaType));
    }

    public function test_throws_when_no_fees_exist(): void
    {
        $visaType = $this->makeVisaType();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No active fees found');

        (new CalculateApplicationFee)->execute($this->makeApplication($visaType));
    }

    public function test_items_contain_fee_snapshot_fields(): void
    {
        $visaType = $this->makeVisaType();

        $fee = VisaFee::create([
            'visa_type_id'   => $visaType->ulid,
            'name'           => 'Visa Fee',
            'amount'         => 8000,
            'currency'       => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active'      => true,
        ]);

        $result = (new CalculateApplicationFee)->execute($this->makeApplication($visaType));

        $item = $result['items']->first();

        $this->assertEquals($fee->ulid, $item['visa_fee_id']);
        $this->assertEquals('Visa Fee', $item['description']);
        $this->assertEquals(8000, $item['unit_amount']);
        $this->assertEquals(1, $item['quantity']);
    }

    private function makeVisaType(): VisaType
    {
        $country = Country::create(['name' => 'Calcu', 'iso2' => 'CL', 'iso3' => 'CLC']);

        return VisaType::create([
            'name'            => 'Tourist',
            'code'            => 'TOURIST_CALC_'.uniqid(),
            'country_id'      => $country->id,
            'processing_days' => 3,
            'validity_days'   => 30,
        ]);
    }

    private function makeApplication(VisaType $visaType): VisaApplication
    {
        $application = new VisaApplication;
        $application->visa_type_id = $visaType->ulid;
        $application->exists = true;

        return $application;
    }
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php artisan test --compact --filter=CalculateApplicationFeeTest
```

Expected: FAIL — class not found.

- [ ] **Step 3: Create `CalculateApplicationFee`**

Create `app/Domain/Payments/Actions/CalculateApplicationFee.php`:

```php
<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Payments\Models\VisaFee;
use Illuminate\Support\Collection;

class CalculateApplicationFee
{
    /**
     * @return array{items: Collection, total_amount: int, currency: string}
     */
    public function execute(VisaApplication $application): array
    {
        $fees = VisaFee::where('visa_type_id', $application->visa_type_id)
            ->where('is_active', true)
            ->where('effective_from', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            })
            ->get();

        if ($fees->isEmpty()) {
            throw new \RuntimeException('No active fees found for this visa type.');
        }

        $items = $fees->map(fn (VisaFee $fee) => [
            'visa_fee_id'  => $fee->ulid,
            'description'  => $fee->name,
            'quantity'     => 1,
            'unit_amount'  => $fee->amount,
            'total_amount' => $fee->amount,
            'currency'     => $fee->currency,
        ]);

        return [
            'items'        => $items,
            'total_amount' => $items->sum('total_amount'),
            'currency'     => $fees->first()->currency,
        ];
    }
}
```

- [ ] **Step 4: Run test — expect PASS**

```bash
php artisan test --compact --filter=CalculateApplicationFeeTest
```

Expected: PASS

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Payments/Actions/CalculateApplicationFee.php tests/Unit/CalculateApplicationFeeTest.php
git commit -m "feat: add CalculateApplicationFee action"
```

---

## Task 7: `CreateCheckoutSession` Action

**Files:**
- Create: `app/Domain/Payments/Actions/CreateCheckoutSession.php`
- Create: `tests/Feature/CreateCheckoutSessionTest.php`

Creates the Stripe Checkout session, persists the `Payment` + `PaymentItem` records, and transitions the application to `payment_pending`. Returns the Stripe checkout URL. The `StripeClient` is resolved from the IoC container — swap it in tests via `$this->app->instance()`.

- [ ] **Step 1: Write failing test**

```bash
php artisan make:test --phpunit CreateCheckoutSessionTest --no-interaction
```

Replace `tests/Feature/CreateCheckoutSessionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Actions\CreateCheckoutSession;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentItem;
use App\Domain\Payments\Models\VisaFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\StripeClient;
use Tests\TestCase;

class CreateCheckoutSessionTest extends TestCase
{
    use RefreshDatabase;

    private VisaApplication $application;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->application = $this->makeApplicationWithFee();
        $this->actor = User::factory()->create();
        $this->mockStripe('cs_test_mock123', 'https://checkout.stripe.com/pay/cs_test_mock123');
    }

    public function test_creates_payment_record_in_processing_status(): void
    {
        (new CreateCheckoutSession)->execute($this->application, $this->actor);

        $this->assertDatabaseHas('payments', [
            'visa_application_id'          => $this->application->ulid,
            'status'                       => 'processing',
            'provider'                     => 'stripe',
            'provider_checkout_session_id' => 'cs_test_mock123',
        ]);
    }

    public function test_creates_payment_items_as_fee_snapshot(): void
    {
        (new CreateCheckoutSession)->execute($this->application, $this->actor);

        $payment = Payment::where('visa_application_id', $this->application->ulid)->firstOrFail();

        $this->assertEquals(1, PaymentItem::where('payment_id', $payment->ulid)->count());
        $this->assertDatabaseHas('payment_items', [
            'payment_id'  => $payment->ulid,
            'description' => 'Application Fee',
            'unit_amount' => 10000,
            'quantity'    => 1,
        ]);
    }

    public function test_transitions_application_to_payment_pending(): void
    {
        (new CreateCheckoutSession)->execute($this->application, $this->actor);

        $this->application->refresh();
        $this->assertEquals(ApplicationStatus::PaymentPending, $this->application->status);
    }

    public function test_writes_status_history_entry(): void
    {
        (new CreateCheckoutSession)->execute($this->application, $this->actor);

        $this->assertDatabaseHas('application_status_histories', [
            'visa_application_id' => $this->application->ulid,
            'from_status'         => 'submitted',
            'to_status'           => 'payment_pending',
            'actor_id'            => $this->actor->id,
        ]);
    }

    public function test_returns_stripe_checkout_url(): void
    {
        $url = (new CreateCheckoutSession)->execute($this->application, $this->actor);

        $this->assertEquals('https://checkout.stripe.com/pay/cs_test_mock123', $url);
    }

    private function mockStripe(string $sessionId, string $sessionUrl): void
    {
        $mockSession = new \stdClass;
        $mockSession->id = $sessionId;
        $mockSession->url = $sessionUrl;
        $mockSession->payment_intent = 'pi_test_mock456';

        $mockSessions = Mockery::mock();
        $mockSessions->shouldReceive('create')->once()->andReturn($mockSession);

        $mockCheckout = new \stdClass;
        $mockCheckout->sessions = $mockSessions;

        $mockStripe = Mockery::mock(StripeClient::class);
        $mockStripe->checkout = $mockCheckout;

        $this->app->instance(StripeClient::class, $mockStripe);
    }

    private function makeApplicationWithFee(): VisaApplication
    {
        $country = Country::create(['name' => 'Checkland', 'iso2' => 'CK', 'iso3' => 'CKL']);
        $visaType = VisaType::create([
            'name'            => 'Tourist',
            'code'            => 'TOURIST_CK_'.uniqid(),
            'country_id'      => $country->id,
            'processing_days' => 3,
            'validity_days'   => 30,
        ]);
        VisaFee::create([
            'visa_type_id'   => $visaType->ulid,
            'name'           => 'Application Fee',
            'amount'         => 10000,
            'currency'       => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active'      => true,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $visaType->ulid,
            'name'         => 'Tourist Form',
            'schema'       => json_encode([]),
        ]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id'                  => $user->id,
            'first_name'               => 'Checkout',
            'last_name'                => 'Tester',
            'date_of_birth'            => '1990-01-01',
            'gender'                   => 'male',
            'nationality_id'           => $country->id,
            'country_of_residence_id'  => $country->id,
            'passport_number'          => 'D12345678',
            'passport_expiry_date'     => '2030-01-01',
            'phone'                    => '+1234567890',
            'address_line_1'           => '1 Checkout Lane',
            'city'                     => 'Checkville',
        ]);

        return VisaApplication::create([
            'tracking_number'        => 'VA-CK-'.uniqid(),
            'applicant_profile_id'   => $profile->ulid,
            'visa_type_id'           => $visaType->ulid,
            'form_template_id'       => $form->ulid,
            'status'                 => ApplicationStatus::Submitted,
        ]);
    }
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php artisan test --compact --filter=CreateCheckoutSessionTest
```

Expected: FAIL — class not found.

- [ ] **Step 3: Create `CreateCheckoutSession`**

Create `app/Domain/Payments/Actions/CreateCheckoutSession.php`:

```php
<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

class CreateCheckoutSession
{
    public function execute(VisaApplication $application, User $actor): string
    {
        $feeData = (new CalculateApplicationFee)->execute($application);

        $stripe = app(StripeClient::class);

        $lineItems = $feeData['items']->map(fn (array $item) => [
            'price_data' => [
                'currency'     => strtolower($item['currency']),
                'product_data' => ['name' => $item['description']],
                'unit_amount'  => $item['unit_amount'],
            ],
            'quantity' => $item['quantity'],
        ])->values()->all();

        $session = $stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items'           => $lineItems,
            'mode'                 => 'payment',
            'success_url'          => config('app.url').'/payment/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'           => config('app.url').'/payment/cancel',
            'metadata'             => [
                'visa_application_ulid' => $application->ulid,
            ],
        ]);

        DB::transaction(function () use ($application, $actor, $feeData, $session): void {
            $payment = Payment::create([
                'visa_application_id'          => $application->ulid,
                'status'                       => 'processing',
                'provider'                     => 'stripe',
                'provider_checkout_session_id' => $session->id,
                'amount_subtotal'              => $feeData['total_amount'],
                'amount_total'                 => $feeData['total_amount'],
                'currency'                     => $feeData['currency'],
            ]);

            foreach ($feeData['items'] as $item) {
                $payment->items()->create([
                    'visa_fee_id'  => $item['visa_fee_id'],
                    'description'  => $item['description'],
                    'quantity'     => $item['quantity'],
                    'unit_amount'  => $item['unit_amount'],
                    'total_amount' => $item['total_amount'],
                ]);
            }

            $fromStatus = $application->status->value;

            $application->update(['status' => ApplicationStatus::PaymentPending]);

            ApplicationStatusHistory::create([
                'visa_application_id' => $application->ulid,
                'from_status'         => $fromStatus,
                'to_status'           => ApplicationStatus::PaymentPending->value,
                'actor_id'            => $actor->id,
                'created_at'          => now(),
            ]);
        });

        return $session->url;
    }
}
```

- [ ] **Step 4: Run test — expect PASS**

```bash
php artisan test --compact --filter=CreateCheckoutSessionTest
```

Expected: PASS

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Payments/Actions/CreateCheckoutSession.php tests/Feature/CreateCheckoutSessionTest.php
git commit -m "feat: add CreateCheckoutSession action"
```

---

## Task 8: `HandlePaymentWebhook` Action (Idempotency)

**Files:**
- Create: `app/Domain/Payments/Actions/HandlePaymentWebhook.php`
- Create: `app/Domain/Payments/Jobs/GenerateReceiptPdf.php` *(stub — full implementation in Task 9)*
- Create: `tests/Feature/HandlePaymentWebhookTest.php`

**Idempotency contract:** Before processing, the handler checks `PaymentWebhookEvent` by `event_id`. If a record already exists with `processed_at != null`, it returns immediately. This means duplicate Stripe deliveries produce exactly one state change.

**Event routing:**
- `checkout.session.completed` + `payment_status == 'paid'` → mark `Payment` succeeded, transition application to `payment_completed`, create `Invoice`, dispatch `GenerateReceiptPdf`
- `payment_intent.payment_failed` → mark `Payment` failed, store failure reason; application stays in `payment_pending`

- [ ] **Step 1: Create `GenerateReceiptPdf` stub job** *(needed so tests can import the class)*

```bash
php artisan make:job --no-interaction GenerateReceiptPdf
```

Move the generated file to `app/Domain/Payments/Jobs/GenerateReceiptPdf.php` and replace its namespace + contents:

```php
<?php

namespace App\Domain\Payments\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReceiptPdf implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $invoiceUlid) {}

    public function handle(): void
    {
        // Implemented in Task 9
    }
}
```

- [ ] **Step 2: Write failing tests**

```bash
php artisan make:test --phpunit HandlePaymentWebhookTest --no-interaction
```

Replace `tests/Feature/HandlePaymentWebhookTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Actions\HandlePaymentWebhook;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Jobs\GenerateReceiptPdf;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HandlePaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_completed_marks_payment_succeeded(): void
    {
        Queue::fake();
        [$application, $payment] = $this->makeApplicationAndPayment();

        (new HandlePaymentWebhook)->execute(
            $this->makeEvent('checkout.session.completed', [
                'id'             => 'cs_test_abc123',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_xyz789',
                'metadata'       => ['visa_application_ulid' => $application->ulid],
            ])
        );

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Succeeded, $payment->status);
        $this->assertEquals('pi_test_xyz789', $payment->provider_payment_intent_id);
        $this->assertNotNull($payment->succeeded_at);
    }

    public function test_checkout_completed_transitions_application_to_payment_completed(): void
    {
        Queue::fake();
        [$application, $payment] = $this->makeApplicationAndPayment();

        (new HandlePaymentWebhook)->execute(
            $this->makeEvent('checkout.session.completed', [
                'id'             => 'cs_test_abc123',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_xyz789',
                'metadata'       => ['visa_application_ulid' => $application->ulid],
            ])
        );

        $this->assertEquals(ApplicationStatus::PaymentCompleted, $application->fresh()->status);
    }

    public function test_checkout_completed_writes_status_history(): void
    {
        Queue::fake();
        [$application, $payment] = $this->makeApplicationAndPayment();

        (new HandlePaymentWebhook)->execute(
            $this->makeEvent('checkout.session.completed', [
                'id'             => 'cs_test_abc123',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_xyz789',
                'metadata'       => ['visa_application_ulid' => $application->ulid],
            ])
        );

        $this->assertDatabaseHas('application_status_histories', [
            'visa_application_id' => $application->ulid,
            'from_status'         => 'payment_pending',
            'to_status'           => 'payment_completed',
            'actor_id'            => null,
        ]);
    }

    public function test_checkout_completed_creates_invoice(): void
    {
        Queue::fake();
        [$application, $payment] = $this->makeApplicationAndPayment();

        (new HandlePaymentWebhook)->execute(
            $this->makeEvent('checkout.session.completed', [
                'id'             => 'cs_test_abc123',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_xyz789',
                'metadata'       => ['visa_application_ulid' => $application->ulid],
            ])
        );

        $this->assertDatabaseHas('invoices', ['payment_id' => $payment->ulid]);
    }

    public function test_checkout_completed_dispatches_generate_receipt_pdf_job(): void
    {
        Queue::fake();
        [$application, $payment] = $this->makeApplicationAndPayment();

        (new HandlePaymentWebhook)->execute(
            $this->makeEvent('checkout.session.completed', [
                'id'             => 'cs_test_abc123',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_xyz789',
                'metadata'       => ['visa_application_ulid' => $application->ulid],
            ])
        );

        Queue::assertPushedOn('pdfs', GenerateReceiptPdf::class);
    }

    public function test_duplicate_event_is_silently_ignored(): void
    {
        Queue::fake();
        [$application, $payment] = $this->makeApplicationAndPayment();

        $eventData = [
            'id'             => 'cs_test_abc123',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_test_xyz789',
            'metadata'       => ['visa_application_ulid' => $application->ulid],
        ];

        (new HandlePaymentWebhook)->execute($this->makeEvent('checkout.session.completed', $eventData, 'evt_dupe_001'));
        (new HandlePaymentWebhook)->execute($this->makeEvent('checkout.session.completed', $eventData, 'evt_dupe_001'));

        $transitionCount = ApplicationStatusHistory::where('visa_application_id', $application->ulid)
            ->where('to_status', 'payment_completed')
            ->count();

        $this->assertEquals(1, $transitionCount);
        Queue::assertPushedTimes(GenerateReceiptPdf::class, 1);
    }

    public function test_failed_payment_marks_payment_failed(): void
    {
        [$application, $payment] = $this->makeApplicationAndPayment('pi_test_fail001');

        (new HandlePaymentWebhook)->execute(
            $this->makeEvent('payment_intent.payment_failed', [
                'id'                 => 'pi_test_fail001',
                'last_payment_error' => ['message' => 'Your card was declined.'],
            ])
        );

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Failed, $payment->status);
        $this->assertEquals('Your card was declined.', $payment->failure_reason);
    }

    public function test_failed_payment_leaves_application_in_payment_pending(): void
    {
        [$application, $payment] = $this->makeApplicationAndPayment('pi_test_fail002');

        (new HandlePaymentWebhook)->execute(
            $this->makeEvent('payment_intent.payment_failed', [
                'id'                 => 'pi_test_fail002',
                'last_payment_error' => ['message' => 'Insufficient funds.'],
            ])
        );

        $this->assertEquals(ApplicationStatus::PaymentPending, $application->fresh()->status);
    }

    private function makeEvent(string $type, array $data, string $eventId = 'evt_test_001'): object
    {
        return (object) [
            'id'   => $eventId,
            'type' => $type,
            'data' => (object) ['object' => (object) $this->deepCastToObject($data)],
        ];
    }

    private function deepCastToObject(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = (object) $this->deepCastToObject($value);
            }
        }

        return $data;
    }

    private function makeApplicationAndPayment(?string $paymentIntentId = null): array
    {
        $country = Country::create(['name' => 'Webhook', 'iso2' => 'WH', 'iso3' => 'WHK']);
        $visaType = VisaType::create([
            'name'            => 'Tourist',
            'code'            => 'TOURIST_WH_'.uniqid(),
            'country_id'      => $country->id,
            'processing_days' => 3,
            'validity_days'   => 30,
        ]);
        $form = FormTemplate::create([
            'visa_type_id' => $visaType->ulid,
            'name'         => 'Tourist Form',
            'schema'       => json_encode([]),
        ]);
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id'                 => $user->id,
            'first_name'              => 'Webhook',
            'last_name'               => 'Tester',
            'date_of_birth'           => '1990-01-01',
            'gender'                  => 'male',
            'nationality_id'          => $country->id,
            'country_of_residence_id' => $country->id,
            'passport_number'         => 'E12345678',
            'passport_expiry_date'    => '2030-01-01',
            'phone'                   => '+1234567890',
            'address_line_1'          => '1 Webhook Way',
            'city'                    => 'Hookville',
        ]);

        $application = VisaApplication::create([
            'tracking_number'      => 'VA-WH-'.uniqid(),
            'applicant_profile_id' => $profile->ulid,
            'visa_type_id'         => $visaType->ulid,
            'form_template_id'     => $form->ulid,
            'status'               => ApplicationStatus::PaymentPending,
        ]);

        $payment = Payment::create([
            'visa_application_id'          => $application->ulid,
            'status'                       => PaymentStatus::Processing,
            'provider'                     => 'stripe',
            'provider_checkout_session_id' => 'cs_test_abc123',
            'provider_payment_intent_id'   => $paymentIntentId,
            'amount_subtotal'              => 10000,
            'amount_total'                 => 10000,
            'currency'                     => 'USD',
        ]);

        return [$application, $payment];
    }
}
```

- [ ] **Step 3: Run test — expect FAIL**

```bash
php artisan test --compact --filter=HandlePaymentWebhookTest
```

Expected: FAIL — `HandlePaymentWebhook` class not found.

- [ ] **Step 4: Create `HandlePaymentWebhook`**

Create `app/Domain/Payments/Actions/HandlePaymentWebhook.php`:

```php
<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\ApplicationStatusHistory;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Jobs\GenerateReceiptPdf;
use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\DB;

class HandlePaymentWebhook
{
    public function execute(object $event): void
    {
        $webhookEvent = PaymentWebhookEvent::where('event_id', $event->id)->first();

        if ($webhookEvent?->processed_at !== null) {
            return;
        }

        if (! $webhookEvent) {
            $webhookEvent = PaymentWebhookEvent::create([
                'provider'   => 'stripe',
                'event_id'   => $event->id,
                'event_type' => $event->type,
                'payload'    => json_decode(json_encode($event), true),
                'created_at' => now(),
            ]);
        }

        try {
            DB::transaction(function () use ($event, $webhookEvent): void {
                match ($event->type) {
                    'checkout.session.completed'    => $this->handleCheckoutCompleted($event->data->object),
                    'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
                    default                         => null,
                };

                $webhookEvent->markProcessed();
            });
        } catch (\Throwable $e) {
            $webhookEvent->markFailed($e->getMessage());
            throw $e;
        }
    }

    private function handleCheckoutCompleted(object $session): void
    {
        if (($session->payment_status ?? '') !== 'paid') {
            return;
        }

        $payment = Payment::where('provider_checkout_session_id', $session->id)->first();

        if (! $payment || $payment->status === PaymentStatus::Succeeded) {
            return;
        }

        $payment->update([
            'status'                     => PaymentStatus::Succeeded,
            'provider_payment_intent_id' => $session->payment_intent ?? null,
            'succeeded_at'               => now(),
        ]);

        $application = $payment->visaApplication;

        $application->update(['status' => ApplicationStatus::PaymentCompleted]);

        ApplicationStatusHistory::create([
            'visa_application_id' => $application->ulid,
            'from_status'         => ApplicationStatus::PaymentPending->value,
            'to_status'           => ApplicationStatus::PaymentCompleted->value,
            'actor_id'            => null,
            'created_at'          => now(),
        ]);

        $invoice = Invoice::create([
            'payment_id'     => $payment->ulid,
            'invoice_number' => $this->generateInvoiceNumber(),
            'issued_at'      => now(),
        ]);

        GenerateReceiptPdf::dispatch($invoice->ulid)->onQueue('pdfs');
    }

    private function handlePaymentFailed(object $intent): void
    {
        $failureMessage = $intent->last_payment_error->message ?? 'Payment failed';

        $payment = Payment::where('provider_payment_intent_id', $intent->id)->first();

        if (! $payment) {
            return;
        }

        $payment->update([
            'status'         => PaymentStatus::Failed,
            'failure_reason' => $failureMessage,
        ]);
    }

    private function generateInvoiceNumber(): string
    {
        $year  = now()->format('Y');
        $count = Invoice::whereYear('created_at', $year)->count() + 1;

        return 'INV-'.$year.'-'.str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }
}
```

- [ ] **Step 5: Run test — expect PASS**

```bash
php artisan test --compact --filter=HandlePaymentWebhookTest
```

Expected: PASS

- [ ] **Step 6: Run full suite**

```bash
php artisan test --compact
```

Expected: all tests pass.

- [ ] **Step 7: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Payments/Actions/HandlePaymentWebhook.php app/Domain/Payments/Jobs/GenerateReceiptPdf.php tests/Feature/HandlePaymentWebhookTest.php
git commit -m "feat: add HandlePaymentWebhook action with idempotency and GenerateReceiptPdf stub"
```

---

## Task 9: `GenerateReceiptPdf` Queued Job + Blade View

**Files:**
- Modify: `app/Domain/Payments/Jobs/GenerateReceiptPdf.php`
- Create: `resources/views/pdfs/receipt.blade.php`

The job loads the `Invoice` → `Payment` → `PaymentItem[]` data, renders a Blade view, generates a PDF via DomPDF, stores it on the `documents` private disk, and updates `invoice.pdf_storage_path`.

- [ ] **Step 1: Create receipt Blade view**

Create `resources/views/pdfs/receipt.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1a1a1a; margin: 40px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .subtitle { color: #6b7280; margin-bottom: 32px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { background: #f3f4f6; text-align: left; padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
        td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
        .total-row td { font-weight: bold; border-top: 2px solid #111; border-bottom: none; }
        .meta { color: #6b7280; font-size: 11px; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h1>Payment Receipt</h1>
    <p class="subtitle">Invoice #{{ $invoice->invoice_number }}</p>

    <table>
        <tr>
            <td class="meta">Invoice Date</td>
            <td>{{ $invoice->issued_at->format('d M Y') }}</td>
            <td class="meta">Application Ref</td>
            <td>{{ $payment->visaApplication->tracking_number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="meta">Payment Status</td>
            <td>{{ ucfirst($payment->status->value) }}</td>
            <td class="meta">Payment Date</td>
            <td>{{ $payment->succeeded_at?->format('d M Y H:i') ?? '—' }}</td>
        </tr>
    </table>

    <table style="margin-top: 32px;">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payment->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="text-right">{{ $item->quantity }}</td>
                <td class="text-right">{{ number_format($item->unit_amount / 100, 2) }} {{ $payment->currency }}</td>
                <td class="text-right">{{ number_format($item->total_amount / 100, 2) }} {{ $payment->currency }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3">Total</td>
                <td class="text-right">{{ number_format($payment->amount_total / 100, 2) }} {{ $payment->currency }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
```

- [ ] **Step 2: Implement `GenerateReceiptPdf` job**

Replace `app/Domain/Payments/Jobs/GenerateReceiptPdf.php`:

```php
<?php

namespace App\Domain\Payments\Jobs;

use App\Domain\Payments\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateReceiptPdf implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $invoiceUlid) {}

    public function handle(): void
    {
        $invoice = Invoice::with(['payment.items', 'payment.visaApplication'])->findOrFail($this->invoiceUlid);
        $payment = $invoice->payment;

        $pdf = Pdf::loadView('pdfs.receipt', compact('invoice', 'payment'));

        $storagePath = 'receipts/'.Str::ulid().'.pdf';

        Storage::disk('documents')->put($storagePath, $pdf->output());

        $invoice->update(['pdf_storage_path' => $storagePath]);
    }
}
```

- [ ] **Step 3: Run full test suite**

```bash
php artisan test --compact
```

Expected: all tests pass (the job is dispatched but not synchronously executed in existing tests — `Queue::fake()` prevents actual execution).

- [ ] **Step 4: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Payments/Jobs/GenerateReceiptPdf.php resources/views/pdfs/receipt.blade.php
git commit -m "feat: implement GenerateReceiptPdf queued job with DomPDF receipt template"
```

---

## Task 10: `StripeWebhookController` + Route

**Files:**
- Create: `app/Http/Controllers/StripeWebhookController.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`

The controller verifies the `Stripe-Signature` header using `\Stripe\Webhook::constructEvent()`, persists the raw event, and delegates to `HandlePaymentWebhook`. The route must be exempt from CSRF middleware.

- [ ] **Step 1: Create `StripeWebhookController`**

```bash
php artisan make:controller StripeWebhookController --no-interaction
```

Replace `app/Http/Controllers/StripeWebhookController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Domain\Payments\Actions\HandlePaymentWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');
        $secret    = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException) {
            return response('Invalid signature', 400);
        } catch (\UnexpectedValueException) {
            return response('Invalid payload', 400);
        }

        try {
            (new HandlePaymentWebhook)->execute($event);
        } catch (\Throwable) {
            return response('Webhook processing failed', 500);
        }

        return response('', 200);
    }
}
```

- [ ] **Step 2: Add webhook route to `routes/web.php`**

Add to `routes/web.php`:

```php
Route::post('/webhooks/stripe', [\App\Http\Controllers\StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe');
```

- [ ] **Step 3: Exempt webhook route from CSRF in `bootstrap/app.php`**

In `bootstrap/app.php`, update the `withMiddleware` block:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
    $middleware->validateCsrfTokens(except: ['/webhooks/*']);
})
```

- [ ] **Step 4: Verify route is registered**

```bash
php artisan route:list --name=webhooks
```

Expected: one route listed — `POST /webhooks/stripe`.

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact
```

Expected: all tests pass.

- [ ] **Step 6: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/StripeWebhookController.php routes/web.php bootstrap/app.php
git commit -m "feat: add StripeWebhookController with signature verification and CSRF exemption"
```

---

## Task 11: `PaymentResource` (Filament) + `PaymentsRelationManager`

**Files:**
- Create: `app/Filament/Resources/Payments/PaymentResource.php`
- Create: `app/Filament/Resources/Payments/Pages/ListPayments.php`
- Create: `app/Filament/Resources/Payments/Pages/ViewPayment.php`
- Create: `app/Filament/Resources/Payments/Tables/PaymentsTable.php`
- Create: `app/Filament/Resources/Payments/Infolists/PaymentInfolist.php`
- Create: `app/Filament/Resources/VisaApplications/RelationManagers/PaymentsRelationManager.php`
- Modify: `app/Filament/Resources/VisaApplications/VisaApplicationResource.php`

`PaymentResource` is **read-only** (no create/edit). Finance officers, admins, and super admins can see it. Amounts are displayed as formatted currency (cents ÷ 100).

- [ ] **Step 1: Create `PaymentsTable`**

Create `app/Filament/Resources/Payments/Tables/PaymentsTable.php`:

```php
<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Domain\Payments\Enums\PaymentStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('visaApplication.tracking_number')
                    ->label('Application')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PaymentStatus $state): string => $state->color()),
                TextColumn::make('amount_total')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state, $record): string => number_format($state / 100, 2).' '.$record->currency),
                TextColumn::make('provider_checkout_session_id')
                    ->label('Session ID')
                    ->limit(20)
                    ->placeholder('—'),
                TextColumn::make('succeeded_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordAction('view');
    }
}
```

- [ ] **Step 2: Create `PaymentInfolist`**

Create `app/Filament/Resources/Payments/Infolists/PaymentInfolist.php`:

```php
<?php

namespace App\Filament\Resources\Payments\Infolists;

use App\Domain\Payments\Enums\PaymentStatus;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment Details')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (PaymentStatus $state): string => $state->color()),
                        TextEntry::make('amount_total')
                            ->label('Total Amount')
                            ->formatStateUsing(fn (int $state, $record): string => number_format($state / 100, 2).' '.$record->currency),
                        TextEntry::make('provider'),
                        TextEntry::make('provider_checkout_session_id')
                            ->label('Checkout Session')
                            ->placeholder('—'),
                        TextEntry::make('provider_payment_intent_id')
                            ->label('Payment Intent')
                            ->placeholder('—'),
                        TextEntry::make('failure_reason')
                            ->placeholder('—'),
                        TextEntry::make('succeeded_at')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ]),
                ]),
            Section::make('Line Items')
                ->schema([
                    RepeatableEntry::make('items')
                        ->schema([
                            TextEntry::make('description'),
                            TextEntry::make('quantity'),
                            TextEntry::make('unit_amount')
                                ->formatStateUsing(fn (int $state, $record): string => number_format($state / 100, 2)),
                            TextEntry::make('total_amount')
                                ->formatStateUsing(fn (int $state, $record): string => number_format($state / 100, 2)),
                        ])
                        ->columns(4),
                ]),
            Section::make('Invoice')
                ->schema([
                    TextEntry::make('invoice.invoice_number')->placeholder('Not yet generated'),
                    TextEntry::make('invoice.issued_at')->dateTime()->placeholder('—'),
                    TextEntry::make('invoice.pdf_storage_path')->label('PDF Path')->placeholder('Pending generation'),
                ]),
        ]);
    }
}
```

- [ ] **Step 3: Create `ListPayments` page**

Create `app/Filament/Resources/Payments/Pages/ListPayments.php`:

```php
<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}
```

- [ ] **Step 4: Create `ViewPayment` page**

Create `app/Filament/Resources/Payments/Pages/ViewPayment.php`:

```php
<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;
}
```

- [ ] **Step 5: Create `PaymentResource`**

Create `app/Filament/Resources/Payments/PaymentResource.php`:

```php
<?php

namespace App\Filament\Resources\Payments;

use App\Domain\Payments\Models\Payment;
use App\Filament\Resources\Payments\Infolists\PaymentInfolist;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'FINANCE';

    protected static ?string $navigationLabel = 'Payments';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['visaApplication', 'items', 'invoice']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view'  => ViewPayment::route('/{record}'),
        ];
    }
}
```

- [ ] **Step 6: Create `PaymentsRelationManager`**

Create `app/Filament/Resources/VisaApplications/RelationManagers/PaymentsRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\VisaApplications\RelationManagers;

use App\Domain\Payments\Enums\PaymentStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ulid')
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PaymentStatus $state): string => $state->color()),
                TextColumn::make('amount_total')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state, $record): string => number_format($state / 100, 2).' '.$record->currency),
                TextColumn::make('provider_checkout_session_id')
                    ->label('Session ID')
                    ->limit(20)
                    ->placeholder('—'),
                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->placeholder('—'),
                TextColumn::make('succeeded_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->headerActions([])
            ->toolbarActions([])
            ->recordActions([]);
    }
}
```

- [ ] **Step 7: Add `PaymentsRelationManager` to `VisaApplicationResource`**

In `app/Filament/Resources/VisaApplications/VisaApplicationResource.php`:

Add import:
```php
use App\Filament\Resources\VisaApplications\RelationManagers\PaymentsRelationManager;
```

Update `getRelations()`:
```php
public static function getRelations(): array
{
    return [
        DocumentsRelationManager::class,
        PaymentsRelationManager::class,
    ];
}
```

- [ ] **Step 8: Run full test suite**

```bash
php artisan test --compact
```

Expected: all tests pass.

- [ ] **Step 9: Verify Filament panel loads**

```bash
php artisan route:list --name=filament.admin.resources.payments
```

Expected: list and view routes registered.

- [ ] **Step 10: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Filament/Resources/Payments/ app/Filament/Resources/VisaApplications/RelationManagers/PaymentsRelationManager.php app/Filament/Resources/VisaApplications/VisaApplicationResource.php
git commit -m "feat: add PaymentResource (read-only) and PaymentsRelationManager to Filament admin"
```

---

## Spec Coverage Self-Review

| Acceptance Criterion | Task |
|---|---|
| Applicant can start a Stripe Checkout session | Task 7 (CreateCheckoutSession) |
| Payment amount from CalculateApplicationFee | Task 6 |
| Duplicate webhook events are idempotent | Task 8 (idempotency test) |
| Payment success transitions application exactly once | Task 8 |
| Failed payment leaves application in payment_pending | Task 8 |
| Receipt PDF dispatched as queued job on success | Tasks 8 + 9 |
| Finance role can view payments but not alter | Tasks 5 + 11 |
| Stripe webhook signature verification | Task 10 |
| Payment amounts calculated server-side | Task 6 (never trusts client totals) |
| PaymentPolicy required | Task 5 |
| Unit: CalculateApplicationFee | Task 6 |
| Feature: webhook idempotency | Task 8 |
| Feature: duplicate event no duplicate records | Task 8 |
| Policy: applicant cannot view another's payment | Task 5 |
