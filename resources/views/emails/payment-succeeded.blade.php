@component('mail::message')
# Payment Received

Dear {{ $applicantName }},

Your payment has been successfully received.

@component('mail::table')
| | |
|:--|:--|
| **Invoice** | {{ $invoiceNumber }} |
| **Amount** | {{ $amount }} |
| **Application** | `{{ $trackingNumber }}` |
@endcomponent

A receipt is available to download from the link below.

@component('mail::button', ['url' => $receiptUrl])
Download Receipt
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
