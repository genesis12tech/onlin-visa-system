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
        .decision-box { padding: 16px; margin: 24px 0; border-radius: 4px; }
        .approved { background: #d1fae5; border-left: 4px solid #10b981; }
        .rejected { background: #fee2e2; border-left: 4px solid #ef4444; }
        .footer { margin-top: 48px; font-size: 11px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 16px; }
    </style>
</head>
<body>
    <h1>Visa Application Decision</h1>
    <p class="subtitle">Reference: {{ $application->tracking_number }}</p>

    <div class="field">
        <div class="label">Applicant</div>
        <div class="value">{{ $applicantName }}</div>
    </div>
    <div class="field">
        <div class="label">Visa Type</div>
        <div class="value">{{ $visaTypeName }}</div>
    </div>
    <div class="field">
        <div class="label">Decision Date</div>
        <div class="value">{{ $application->decision_at?->format('d M Y') ?? '—' }}</div>
    </div>

    <div class="decision-box {{ $application->status->value === 'approved' ? 'approved' : 'rejected' }}">
        <strong>Decision: {{ ucfirst($application->status->value) }}</strong>
        @if($application->decision_reason)
            <p style="margin: 8px 0 0;">{{ $application->decision_reason }}</p>
        @endif
    </div>

    <div class="footer">
        Generated on {{ now()->format('d M Y') }}. Tracking number: {{ $application->tracking_number }}.
    </div>
</body>
</html>
