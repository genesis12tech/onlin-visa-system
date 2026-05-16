<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1a1a1a; margin: 40px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .subtitle { color: #6b7280; margin-bottom: 32px; }
        .field { margin-bottom: 12px; }
        .label { color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
        .value { font-size: 14px; margin-top: 2px; }
        .highlight-box { padding: 16px; margin: 24px 0; background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px; }
        .footer { margin-top: 48px; font-size: 11px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 16px; }
    </style>
</head>
<body>
    <h1>Appointment Confirmation</h1>
    <p class="subtitle">Application Ref: {{ $application->tracking_number }}</p>

    <div class="field">
        <div class="label">Applicant</div>
        <div class="value">{{ $applicantProfile->full_name }}</div>
    </div>
    <div class="field">
        <div class="label">Visa Type</div>
        <div class="value">{{ $application->visaType->name }}</div>
    </div>

    <div class="highlight-box">
        <div class="field">
            <div class="label">Appointment Date &amp; Time</div>
            <div class="value" style="font-size: 16px; font-weight: bold;">{{ $appointment->appointment_at->format('l, F j, Y \a\t g:i A') }}</div>
        </div>
        @if($appointment->location)
        <div class="field" style="margin-top: 12px;">
            <div class="label">Location</div>
            <div class="value">{{ $appointment->location }}</div>
        </div>
        @endif
    </div>

    @if($appointment->instructions)
    <div class="field">
        <div class="label">Instructions</div>
        <div class="value">{{ $appointment->instructions }}</div>
    </div>
    @endif

    <div class="footer">
        Please bring this document and a valid photo ID to your appointment. Generated on {{ now()->format('d M Y') }}.
    </div>
</body>
</html>
