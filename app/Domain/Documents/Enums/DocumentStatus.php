<?php

namespace App\Domain\Documents\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Uploaded = 'uploaded';
    case PendingScan = 'pending_scan';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Infected = 'infected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Uploaded => 'Uploaded',
            self::PendingScan => 'Scanning',
            self::UnderReview => 'Under Review',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Infected => 'Security Failed',
        };
    }
}
