<?php

namespace App\Domain\Applications\Enums;

enum RejectionReason: string
{
    case InsufficientFinancialEvidence = 'insufficient_financial_evidence';
    case InvalidOrExpiredDocument = 'invalid_or_expired_document';
    case IncompleteApplication = 'incomplete_application';
    case PreviousViolation = 'previous_visa_violation';
    case IneligibleNationality = 'ineligible_nationality';
    case DoesNotMeetCriteria = 'does_not_meet_eligibility_criteria';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::InsufficientFinancialEvidence => 'Insufficient financial evidence',
            self::InvalidOrExpiredDocument => 'Invalid or expired travel document',
            self::IncompleteApplication => 'Incomplete application',
            self::PreviousViolation => 'Previous visa violation',
            self::IneligibleNationality => 'Ineligible nationality',
            self::DoesNotMeetCriteria => 'Does not meet eligibility criteria',
            self::Other => 'Other',
        };
    }
}
