<?php

namespace App\Http\Controllers;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $profile = $request->user()->applicantProfile;

        $applications = $profile
            ? VisaApplication::where('applicant_profile_id', $profile->ulid)
                ->with(['visaType', 'visaType.country'])
                ->latest()
                ->get()
            : collect();

        $actionRequired = $applications->filter(fn ($app) => in_array($app->status, [
            ApplicationStatus::AdditionalInfoRequested,
            ApplicationStatus::PaymentPending,
            ApplicationStatus::DocsRequired,
        ], strict: true));

        return view('pages.dashboard', compact('profile', 'applications', 'actionRequired'));
    }
}
