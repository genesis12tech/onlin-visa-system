<?php

namespace App\Http\Controllers;

use App\Domain\Payments\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function success(Request $request): View|RedirectResponse
    {
        $sessionId = $request->query('session_id', '');

        if (! $sessionId) {
            return redirect()->route('dashboard');
        }

        $payment = Payment::where('provider_checkout_session_id', $sessionId)
            ->with(['visaApplication.applicantProfile', 'invoice'])
            ->first();

        if (! $payment || ! $payment->visaApplication) {
            return redirect()->route('dashboard');
        }

        Gate::authorize('view', $payment->visaApplication);

        return view('pages.payment.success', [
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'application' => $payment->visaApplication,
        ]);
    }
}
