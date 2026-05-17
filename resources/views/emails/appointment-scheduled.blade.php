@component('mail::message')
# Appointment Scheduled

Dear {{ $applicantName }},

An appointment has been scheduled for your visa application **`{{ $trackingNumber }}`**.

@component('mail::panel')
**Date & Time:** {{ $appointmentAt }}

@if($location)
**Location:** {{ $location }}
@endif
@endcomponent

@if($instructions)
**Instructions:** {{ $instructions }}

@endif
Please bring this confirmation and a valid photo ID to your appointment.

@component('mail::button', ['url' => $applicationUrl])
View Application
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
