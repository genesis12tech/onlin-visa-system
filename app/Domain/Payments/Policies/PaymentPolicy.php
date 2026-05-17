<?php

namespace App\Domain\Payments\Policies;

use App\Domain\Payments\Models\Invoice;
use App\Domain\Payments\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'finance_officer']);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'finance_officer']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function downloadReceipt(User $user, Invoice $invoice): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'finance_officer'])) {
            return true;
        }

        return $invoice->payment?->visaApplication?->applicantProfile?->user_id === $user->id;
    }
}
