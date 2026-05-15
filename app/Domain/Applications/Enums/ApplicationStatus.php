<?php

namespace App\Domain\Applications\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case PaymentPending = 'payment_pending';
    case PaymentCompleted = 'payment_completed';
    case UnderReview = 'under_review';
    case AdditionalInfoRequested = 'additional_info_requested';
    case DocsRequired = 'docs_required';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::PaymentPending => 'Payment Pending',
            self::PaymentCompleted => 'Payment Completed',
            self::UnderReview => 'In Review',
            self::AdditionalInfoRequested => 'Info Requested',
            self::DocsRequired => 'Docs Required',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft, self::Withdrawn => 'gray',
            self::Submitted => 'info',
            self::PaymentPending => 'warning',
            self::PaymentCompleted => 'success',
            self::UnderReview => 'warning',
            self::AdditionalInfoRequested => 'primary',
            self::DocsRequired => 'primary',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    public function badgeColor(): string
    {
        return $this->color();
    }
}
