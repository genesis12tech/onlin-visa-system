<?php

namespace App\Providers;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Applications\Policies\VisaApplicationPolicy;
use App\Domain\Documents\Models\ApplicationDocument;
use App\Domain\Documents\Policies\ApplicationDocumentPolicy;
use App\Domain\Identity\Models\ApplicantProfile;
use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Models\VisaFee;
use App\Models\User;
use App\Policies\ApplicantProfilePolicy;
use App\Policies\CountryPolicy;
use App\Policies\UserPolicy;
use App\Policies\VisaFeePolicy;
use App\Policies\VisaTypePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Country::class, CountryPolicy::class);
        Gate::policy(VisaType::class, VisaTypePolicy::class);
        Gate::policy(VisaFee::class, VisaFeePolicy::class);
        Gate::policy(ApplicantProfile::class, ApplicantProfilePolicy::class);
        Gate::policy(VisaApplication::class, VisaApplicationPolicy::class);
        Gate::policy(ApplicationDocument::class, ApplicationDocumentPolicy::class);
    }
}
