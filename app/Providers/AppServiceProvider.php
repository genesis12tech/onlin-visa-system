<?php

namespace App\Providers;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Applications\Policies\VisaApplicationPolicy;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Policies\ApplicationDocumentPolicy;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\VisaFee;
use App\Domain\Payments\Policies\PaymentPolicy;
use App\Domain\Reporting\Models\ApplicationExport;
use App\Domain\Reporting\Policies\ApplicationExportPolicy;
use App\Models\User;
use App\Policies\ApplicantProfilePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\CountryPolicy;
use App\Policies\UserPolicy;
use App\Policies\VisaFeePolicy;
use App\Policies\VisaTypePolicy;
use App\Support\AuditLog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StripeClient::class, fn () => new StripeClient(config('services.stripe.secret')));
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Country::class, CountryPolicy::class);
        Gate::policy(VisaType::class, VisaTypePolicy::class);
        Gate::policy(VisaFee::class, VisaFeePolicy::class);
        Gate::policy(ApplicantProfile::class, ApplicantProfilePolicy::class);
        Gate::policy(VisaApplication::class, VisaApplicationPolicy::class);
        Gate::policy(ApplicationDocument::class, ApplicationDocumentPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Invoice::class, PaymentPolicy::class);
        Gate::policy(ApplicationExport::class, ApplicationExportPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);

        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('document-download', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('mfa-otp', function (Request $request) {
            return Limit::perMinutes(15, 3)->by($request->input('email', $request->ip()));
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
