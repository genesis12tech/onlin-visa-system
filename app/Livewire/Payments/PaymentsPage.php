<?php

namespace App\Livewire\Payments;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\View\View;
use Livewire\Component;

class PaymentsPage extends Component
{
    public int $pendingCount = 0;

    /** @var array<int, array<string, mixed>> */
    public array $payments = [];

    /** @var array<int, array<string, mixed>> */
    public array $pendingApplications = [];

    public function mount(): void
    {
        $profile = auth()->user()->applicantProfile;

        if (! $profile) {
            return;
        }

        $applications = VisaApplication::where('applicant_profile_id', $profile->ulid)
            ->with([
                'visaType',
                'payments',
                'payments.invoice',
            ])
            ->get();

        $pendingStatuses = [ApplicationStatus::PaymentPending];
        $pending = $applications->filter(fn ($app) => in_array($app->status, $pendingStatuses, true));

        $this->pendingCount = $pending->count();

        $this->pendingApplications = $pending->map(fn (VisaApplication $app) => [
            'tracking_number' => $app->tracking_number,
            'visa_type_name' => $app->visaType->name,
            'pay_url' => route('applications.pay', $app->tracking_number),
        ])->values()->all();

        $this->payments = $applications
            ->flatMap(fn (VisaApplication $app) => $app->payments->map(fn ($payment) => [
                'ulid' => $payment->ulid,
                'tracking_number' => $app->tracking_number,
                'visa_type_name' => $app->visaType->name,
                'amount' => number_format($payment->amount_total / 100, 2),
                'currency' => strtoupper($payment->currency),
                'provider' => ucfirst($payment->provider),
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'date' => $payment->created_at->format('d M Y'),
                'created_at_ts' => $payment->created_at->timestamp,
                'invoice_ulid' => $payment->invoice?->ulid,
                'has_receipt_pdf' => (bool) $payment->invoice?->pdf_storage_path,
                'pay_url' => in_array($app->status, $pendingStatuses, true)
                    ? route('applications.pay', $app->tracking_number)
                    : null,
            ]))
            ->sortByDesc('created_at_ts')
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.payments.payments-page')
            ->layout('layouts.app', ['title' => 'Payments']);
    }
}
