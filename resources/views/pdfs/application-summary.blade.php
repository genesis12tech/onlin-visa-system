<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1a1a1a; margin: 40px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .subtitle { color: #6b7280; margin-bottom: 32px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; margin-bottom: 24px; }
        th { background: #f3f4f6; text-align: left; padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
        td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
        .footer { margin-top: 48px; font-size: 11px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 16px; }
    </style>
</head>
<body>
    <h1>Application Submission Confirmation</h1>
    <p class="subtitle">Tracking Number: {{ $application->tracking_number }}</p>

    <table>
        <tr><th colspan="2">Applicant Details</th></tr>
        <tr>
            <td>Full Name</td>
            <td>{{ $applicantProfile->full_name }}</td>
        </tr>
        <tr>
            <td>Date of Birth</td>
            <td>{{ $applicantProfile->date_of_birth->format('d M Y') }}</td>
        </tr>
        <tr>
            <td>Passport Number</td>
            <td>{{ $applicantProfile->passport_number }}</td>
        </tr>
    </table>

    <table>
        <tr><th colspan="2">Application Details</th></tr>
        <tr>
            <td>Visa Type</td>
            <td>{{ $application->visaType->name }}</td>
        </tr>
        <tr>
            <td>Submitted On</td>
            <td>{{ $application->submitted_at?->format('d M Y \a\t H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <td>Status</td>
            <td>{{ $application->status->label() }}</td>
        </tr>
    </table>

    <div class="footer">
        Keep this document as proof of your submission. Generated on {{ now()->format('d M Y') }}.
    </div>
</body>
</html>
