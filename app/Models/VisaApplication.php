<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisaApplication extends Model
{
    public static function statusColor(string $status): string
    {
        return match ($status) {
            'Submitted' => 'info',
            'Under review' => 'warning',
            'Approved' => 'success',
            'Docs required' => 'primary',
            'Rejected' => 'danger',
            default => 'gray',
        };
    }
}
