<?php

namespace Modules\Attendance\Infrastructure\Persistence;

use App\Services\WorkforceCacheRegistry;
use Illuminate\Database\ConnectionInterface;
use Modules\Attendance\Application\DTOs\ScheduleRuleEditorViewModel;
use Modules\Attendance\Domain\Repositories\ScheduleRuleReadRepositoryInterface;

final readonly class EloquentScheduleRuleReadRepository implements ScheduleRuleReadRepositoryInterface
{
    public function __construct(private ConnectionInterface $db, private WorkforceCacheRegistry $cache) {}

    public function editor(string $type, int $companyId, int $parentId): ScheduleRuleEditorViewModel
    {
        return $this->cache->remember($companyId, 'workforce', "{$type}-rule-editor:{$parentId}", 300, fn (): ScheduleRuleEditorViewModel => $this->editorUncached($type, $companyId, $parentId));
    }

    private function editorUncached(string $type, int $companyId, int $parentId): ScheduleRuleEditorViewModel
    {
        $table = $type === 'calendar' ? 'work_calendars' : 'work_shifts';
        $row = $this->db->table($table)->where('company_id', $companyId)->where('id', $parentId)->first();
        abort_if($row === null, 404);
        $parent = ['id' => $this->int($row, 'id'), 'code' => $this->text($row, 'code'), 'name' => $this->text($row, 'name'), 'status' => $this->text($row, 'status'), 'version' => $this->int($row, 'version')];
        if ($type === 'shift') {
            $parent += ['starts_at' => $this->text($row, 'starts_at'), 'ends_at' => $this->text($row, 'ends_at'), 'crosses_midnight' => (bool) ($row->crosses_midnight ?? false)];
        }
        $ruleQuery = $type === 'calendar' ? $this->db->table('work_calendar_days')->where('work_calendar_id', $parentId)->orderBy('weekday') : $this->db->table('shift_break_rules')->where('work_shift_id', $parentId)->where('status', 'active')->orderBy('sort_order')->orderBy('id');
        /** @var list<array<string,bool|int|string|null>> $rules */
        $rules = $ruleQuery->get()->map(static fn (object $item): array => (array) $item)->all();
        $shifts = $this->db->table('work_shifts')->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name'])->map(fn (object $item): array => ['id' => $this->int($item, 'id'), 'code' => $this->text($item, 'code'), 'name' => $this->text($item, 'name')])->all();

        return new ScheduleRuleEditorViewModel($type, $parent, $rules, array_values($shifts));
    }

    private function int(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : '';
    }
}
