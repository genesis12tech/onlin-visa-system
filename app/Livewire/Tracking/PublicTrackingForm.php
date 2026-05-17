<?php

namespace App\Livewire\Tracking;

use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

class PublicTrackingForm extends Component
{
    public string $trackingNumber = '';

    public ?array $result = null;

    public bool $notFound = false;

    protected array $rules = [
        'trackingNumber' => 'required|string|min:3|max:50',
    ];

    public function submit(): void
    {
        $this->notFound = false;
        $this->result = null;

        try {
            $this->validate();
        } catch (ValidationException) {
            $this->notFound = true;

            return;
        }

        $application = VisaApplication::where('tracking_number', $this->trackingNumber)
            ->with([
                'visaType',
                'statusHistories' => fn ($q) => $q->whereNotNull('public_label')->orderBy('created_at'),
            ])
            ->first();

        if ($application === null) {
            $this->notFound = true;

            return;
        }

        $this->result = [
            'tracking_number' => $application->tracking_number,
            'visa_type_name' => $application->visaType->name,
            'status' => $application->status,
            'histories' => $application->statusHistories
                ->map(fn ($h) => [
                    'public_label' => $h->public_label,
                    'created_at' => $h->created_at->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    public function render(): View
    {
        return view('livewire.tracking.public-tracking-form')
            ->layout('layouts.guest', ['title' => 'Track Your Application']);
    }
}
