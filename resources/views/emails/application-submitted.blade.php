@component('mail::message')
# Application Received

Dear {{ $applicantName }},

Thank you — your visa application has been received and is now being processed.

@component('mail::table')
| | |
|:--|:--|
| **Tracking Number** | `{{ $trackingNumber }}` |
| **Submitted** | {{ $submittedAt }} |
@endcomponent

We will notify you as your application progresses. You can check the current status at any time from your dashboard.

@component('mail::button', ['url' => $dashboardUrl])
View Dashboard
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
