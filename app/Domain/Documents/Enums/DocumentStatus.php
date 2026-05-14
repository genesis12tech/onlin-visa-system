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
}
