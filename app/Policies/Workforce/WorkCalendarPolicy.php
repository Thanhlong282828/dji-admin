<?php

namespace App\Policies\Workforce;

use App\Models\User;

final readonly class WorkCalendarPolicy extends AbstractWorkforcePolicy
{
    public function viewAny(User $u, int $c): bool
    {
        return $this->company($u, 'work-calendar.view', $c);
    }

    public function view(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'work-calendar.view', $c, 'work_calendars', $id);
    }

    public function create(User $u, int $c): bool
    {
        return $this->company($u, 'work-calendar.manage', $c);
    }

    public function update(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'work-calendar.manage', $c, 'work_calendars', $id) && $this->status('work_calendars', $id) !== 'archived';
    }

    public function activate(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id);
    }

    public function deactivate(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id);
    }

    public function archive(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id);
    }

    public function manageDays(User $u, int $c, int $id): bool
    {
        return $this->update($u, $c, $id);
    }

    public function export(User $u, int $c): bool
    {
        return $this->viewAny($u, $c);
    }
}
