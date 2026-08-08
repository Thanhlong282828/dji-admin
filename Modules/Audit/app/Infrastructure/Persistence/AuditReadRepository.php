<?php

namespace Modules\Audit\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Audit\Application\DTOs\AuditPageViewModel;
use Modules\Audit\Domain\Services\AuditIntegrityService;

final readonly class AuditReadRepository
{
    public function __construct(private ConnectionInterface $db, private AuditIntegrityService $integrity) {}

    public function page(int $company, string $page, ?int $id, ?string $search): AuditPageViewModel
    {
        if ($page === 'dashboard') {
            return new AuditPageViewModel('Audit Dashboard', [], ['total_events' => $this->count($company), 'today_events' => $this->db->table('audit_events')->where('company_id', $company)->whereDate('occurred_at', today())->count(), 'integrity_failures' => 0, 'actors' => $this->db->table('audit_events')->where('company_id', $company)->distinct()->count('actor_id'), 'resources' => $this->db->table('audit_events')->where('company_id', $company)->distinct()->count('resource_type')]);
        }[$table,$title] = $this->map($page);
        $q = $this->db->table($table)->where('company_id', $company);
        if ($id !== null) {
            $q->where('id', $id);
        }if ($search !== null && $search !== '' && $table === 'audit_events') {
            $q->where(function (Builder $builder) use ($search): void {
                $builder->where('action', 'like', '%'.$search.'%')
                    ->orWhere('resource_type', 'like', '%'.$search.'%')
                    ->orWhere('resource_id', 'like', '%'.$search.'%');
            });
        }

        $rows = $q->orderByDesc('id')->limit(100)->get();
        if ($id !== null && $rows->isEmpty()) {
            abort(404);
        }
        if ($page === 'event' && $id !== null) {
            abort_unless($this->integrity->verify($company, $id), 409, 'Audit event integrity verification failed.');
        }

        /** @var list<array<string, bool|float|int|string|null>> $items */
        $items = [];
        foreach ($rows as $row) {
            $item = [];
            foreach (get_object_vars($row) as $key => $value) {
                if (in_array($key, ['storage_path', 'metadata'], true)) {
                    continue;
                }

                if (is_string($key) && (is_scalar($value) || $value === null)) {
                    $item[$key] = $value;
                }
            }

            $items[] = $item;
        }

        return new AuditPageViewModel($title, $items);
    }

    private function count(int $company): int
    {
        return $this->db->table('audit_events')->where('company_id', $company)->count();
    }

    /** @return array{string,string} */
    private function map(string $page): array
    {
        return match ($page) {
            'timeline' => ['audit_events', 'Audit Timeline'],
            'event' => ['audit_events', 'Audit Event Detail'],
            'actor' => ['audit_events', 'Actor History'],
            'resource' => ['audit_events', 'Resource History'],
            'exports' => ['audit_exports', 'Compliance Exports'],
            'retention' => ['audit_retention_policies', 'Retention Policies'],
            default => abort(404),
        };
    }
}
