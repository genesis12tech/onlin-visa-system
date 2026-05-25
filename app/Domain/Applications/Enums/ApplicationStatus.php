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

    /** @return string[] */
    public static function inProgressValues(): array
    {
        return [
            self::Submitted->value,
            self::PaymentPending->value,
            self::PaymentCompleted->value,
            self::UnderReview->value,
            self::AdditionalInfoRequested->value,
            self::DocsRequired->value,
        ];
    }

    public function colour(): string
    {
        return match ($this) {
            self::Draft, self::Withdrawn => 'gray',
            self::Submitted, self::PaymentPending => 'blue',
            self::PaymentCompleted, self::UnderReview, self::DocsRequired => 'purple',
            self::AdditionalInfoRequested => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
    }

    public function publicLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted, self::PaymentPending => 'Submitted',
            self::PaymentCompleted, self::UnderReview, self::DocsRequired => 'In review',
            self::AdditionalInfoRequested => 'Action required',
            self::Approved => 'Approved',
            self::Rejected => 'Not approved',
            self::Withdrawn => 'Closed',
        };
    }

    public function borderClass(): string
    {
        return match ($this) {
            self::Approved => 'border-l-teal-600',
            self::Rejected => 'border-l-red-500',
            self::UnderReview, self::AdditionalInfoRequested, self::DocsRequired => 'border-l-amber-500',
            self::Submitted, self::PaymentPending, self::PaymentCompleted => 'border-l-blue-500',
            self::Draft, self::Withdrawn => 'border-l-gray-300',
        };
    }
}
