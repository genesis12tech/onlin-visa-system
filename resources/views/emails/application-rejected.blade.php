@component('mail::message')
# Visa Application Decision

Dear {{ $applicantName }},

We regret to inform you that your visa application has been **rejected**.

@component('mail::table')
| | |
|:--|:--|
| **Tracking Number** | `{{ $trackingNumber }}` |
| **Decision Date** | {{ $decisionAt }} |
@endcomponent

@if($decisionReason)
@component('mail::panel')
**Reason:** {{ $decisionReason }}
@endcomponent
@endif

You may log in to your dashboard to download the decision letter.

@component('mail::button', ['url' => $dashboardUrl])
View Dashboard
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
