@component('mail::message')
# Visa Application Approved

Dear {{ $applicantName }},

We are pleased to inform you that your visa application has been **approved**.

@component('mail::table')
| | |
|:--|:--|
| **Tracking Number** | `{{ $trackingNumber }}` |
| **Decision Date** | {{ $decisionAt }} |
@endcomponent

@if($decisionReason)
@component('mail::panel')
**Note:** {{ $decisionReason }}
@endcomponent
@endif

Please log in to your dashboard to download your decision letter and review next steps.

@component('mail::button', ['url' => $dashboardUrl])
View Dashboard
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
