@component('mail::message')
# Additional Information Required

Dear {{ $applicantName }},

Additional information has been requested for your visa application **`{{ $trackingNumber }}`**.

@component('mail::panel')
{{ $officerMessage }}
@endcomponent

Please log in to your application to review the request and provide the required information.

@component('mail::button', ['url' => $applicationUrl])
View Application
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
