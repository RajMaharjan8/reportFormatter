<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Report $report): bool
    {
        return $report->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return ! $user->hasReachedReportLimit();
    }

    public function update(User $user, Report $report): bool
    {
        return $report->user_id === $user->id;
    }

    public function delete(User $user, Report $report): bool
    {
        return $report->user_id === $user->id;
    }
}
