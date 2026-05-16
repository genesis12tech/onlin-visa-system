<?php

namespace App\Domain\Reporting\Policies;

use App\Domain\Reporting\Enums\ExportStatus;
use App\Domain\Reporting\Models\ApplicationExport;
use App\Models\User;

class ApplicationExportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'finance_officer']);
    }

    public function view(User $user, ApplicationExport $export): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin'])
            || $export->requested_by === $user->id;
    }

    public function download(User $user, ApplicationExport $export): bool
    {
        return $export->status === ExportStatus::Ready
            && ($user->hasAnyRole(['super_admin', 'admin'])
                || $export->requested_by === $user->id);
    }
}
