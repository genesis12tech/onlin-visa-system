<?php

namespace App\Domain\Applications\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case DocsRequired = 'docs_required';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::UnderReview => 'In Review',
            self::DocsRequired => 'Docs Required',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'info',
            self::UnderReview => 'warning',
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
