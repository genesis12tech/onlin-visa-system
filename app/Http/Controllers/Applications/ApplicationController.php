<?php

namespace App\Http\Controllers\Applications;

use App\Domain\Applications\Actions\CreateDraftApplication;
use App\Domain\Applications\Actions\WithdrawApplication;
use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\FormTemplate;
use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\StartApplicationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function start(Request $request): View
    {
        Gate::authorize('create', VisaApplication::class);

        $visaTypes = VisaType::where('is_active', true)
            ->with('country')
            ->orderBy('name')
            ->get();

        return view('pages.applications.start', compact('visaTypes'));
    }

    public function store(StartApplicationRequest $request): RedirectResponse
    {
        Gate::authorize('create', VisaApplication::class);

        $visaType = VisaType::where('ulid', $request->validated('visa_type_ulid'))->firstOrFail();

        $formTemplate = FormTemplate::where('visa_type_id', $visaType->ulid)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->firstOrFail();

        $profile = $request->user()->applicantProfile;

        $existing = VisaApplication::where('applicant_profile_id', $profile->ulid)
            ->where('visa_type_id', $visaType->ulid)
            ->where('status', ApplicationStatus::Draft->value)
            ->first();

        if ($existing) {
            return redirect()->route('applications.wizard', $existing->tracking_number);
        }

        $application = app(CreateDraftApplication::class)->execute(
            applicantProfile: $profile,
            visaType: $visaType,
            formTemplate: $formTemplate,
        );

        return redirect()->route('applications.wizard', $application->tracking_number);
    }

    public function withdraw(Request $request, string $tracking): RedirectResponse
    {
        $application = VisaApplication::where('tracking_number', $tracking)->firstOrFail();

        Gate::authorize('withdraw', $application);

        WithdrawApplication::run($application, $request->user());

        return redirect()->route('dashboard')
            ->with('success', 'Your application has been withdrawn.');
    }
}
