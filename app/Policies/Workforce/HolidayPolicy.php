<?php

namespace App\Policies\Workforce;

use App\Models\User;

final readonly class HolidayPolicy extends AbstractWorkforcePolicy
{
    public function viewAny(User $u, int $c): bool
    {
        return $this->company($u, 'work-calendar.view', $c);
    }

    public function view(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'work-calendar.view', $c, 'company_holidays', $id);
    }

    public function create(User $u, int $c): bool
    {
        return $this->company($u, 'work-calendar.manage', $c);
    }

    public function update(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'work-calendar.manage', $c, 'company_holidays', $id);
    }

    public function archive(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id);
    }

    public function export(User $u, int $c): bool
    {
        return $this->viewAny($u, $c);
    }
}
