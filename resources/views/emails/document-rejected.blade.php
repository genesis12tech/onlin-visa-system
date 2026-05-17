@component('mail::message')
# Document Rejected — Action Required

Dear {{ $applicantName }},

Your **{{ $documentTypeName }}** for application **`{{ $trackingNumber }}`** has been rejected.

@component('mail::panel')
**Reason:** {{ $rejectionReason }}
@endcomponent

Please upload a replacement document to continue processing your application. All required documents must be accepted before your application can proceed to review.

@component('mail::button', ['url' => $applicationUrl])
Upload Replacement
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
