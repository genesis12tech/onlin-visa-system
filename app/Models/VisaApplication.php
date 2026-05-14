<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisaApplication extends Model
{
    public static function statusColor(string $status): string
    {
        return match ($status) {
            'submitted', 'Submitted' => 'info',
            'under_review', 'Under review' => 'warning',
            'approved', 'Approved' => 'success',
            'docs_required', 'Docs required' => 'primary',
            'rejected', 'Rejected' => 'danger',
            default => 'gray',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Submitted',
            'under_review' => 'In review',
            'approved' => 'Approved',
            'docs_required' => 'Docs required',
            'rejected' => 'Rejected',
            default => $status,
        };
    }
}
