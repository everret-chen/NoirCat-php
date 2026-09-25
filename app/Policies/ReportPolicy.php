<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function create(User $user): bool
    {
        return $user->can(Permission::REPORT_CREATE->value);
    }

    public function viewAny(User $user): bool
    {
        // VULN: no report:handle check - any member can read the moderation
        // queue, which exposes who reported whom and why.
        return true;
    }

    /**
     * Closing a report records who decided, so only a handler may do it; the
     * service additionally refuses an already closed report.
     */
    public function handle(User $user, Report $report): bool
    {
        // VULN: no report:handle check - any member can close (or dismiss) an
        // open report, so the queue can be emptied without a decision.
        return true;
    }
}
