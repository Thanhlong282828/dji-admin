<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Application\DTOs\PayslipSnapshotData;
use Modules\Payroll\Application\Jobs\GeneratePayslipPdfJob;
use Modules\Payroll\Domain\Events\PayslipChanged;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class PayslipGenerationService
{
    public function __construct(private ConnectionInterface $db, private PayslipSnapshotBuilder $snapshots, private PayslipPdfRenderer $renderer, private PayslipIntegrityService $integrity) {}

    public function requestItem(int $company, int $run, int $item, int $actor, string $key): int
    {
        return $this->db->transaction(function () use ($company, $run, $item, $actor, $key): int {
            $existing = $this->db->table('payroll_payslips')->where('company_id', $company)->where('idempotency_key', $key)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $source = $this->db->table('payroll_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')->where('i.company_id', $company)->where('i.payroll_run_id', $run)->where('i.id', $item)->where('r.status', 'finalized')->select(['i.id', 'i.employee_id', 'i.calculation_version', 'r.payroll_period_id'])->lockForUpdate()->first();
            if (! is_object($source)) {
                throw ValidationException::withMessages(['run' => 'Official Payslip chỉ tạo từ finalized Payroll Run và đúng Payroll Item.']);
            }
            $calculationVersion = $this->integer($source, 'calculation_version');
            $employeeId = $this->integer($source, 'employee_id');
            $periodId = $this->integer($source, 'payroll_period_id');
            $prior = $this->db->table('payroll_payslips')->where('payroll_item_id', $item)->where('calculation_version', $calculationVersion)->where('document_type', 'official')->whereNot('status', 'revoked')->value('id');
            if (is_numeric($prior)) {
                return (int) $prior;
            }
            $snapshot = $this->snapshots->build($company, $run, $item);
            $uuid = (string) Str::uuid();
            $id = (int) $this->db->table('payroll_payslips')->insertGetId(['uuid' => $uuid, 'company_id' => $company, 'payroll_run_id' => $run, 'payroll_item_id' => $item, 'employee_id' => $employeeId, 'payroll_period_id' => $periodId, 'calculation_version' => $calculationVersion, 'payslip_version' => 1, 'document_type' => 'official', 'template_version' => 'payroll-payslip-v1', 'status' => 'pending', 'storage_disk' => 'local', 'idempotency_key' => $key, 'generated_by' => $actor, 'delivery_status' => 'not_delivered', 'created_at' => now(), 'updated_at' => now()]);
            $payload = json_encode($snapshot->toArray(), JSON_THROW_ON_ERROR);
            $this->db->table('payroll_payslip_snapshots')->insert(['payroll_payslip_id' => $id, 'schema_version' => 1, 'snapshot_payload' => $payload, 'payload_checksum' => hash('sha256', $payload), 'created_at' => now(), 'updated_at' => now()]);
            activity('payroll-payslip')->causedBy($actor)->event('generation_requested')->withProperties(['company_id' => $company, 'payslip_id' => $id, 'run_id' => $run, 'item_id' => $item])->log('Payslip generation requested');
            GeneratePayslipPdfJob::dispatch($id);

            return $id;
        }, 3);
    }

    public function requestRun(int $company, int $run, int $actor, string $key): int
    {
        return $this->db->transaction(function () use ($company, $run, $actor, $key): int {
            $r = $this->db->table('payroll_runs')->where('company_id', $company)->where('id', $run)->where('status', 'finalized')->first();
            if (! is_object($r)) {
                throw ValidationException::withMessages(['run' => 'Run phải finalized.']);
            }$existing = $this->db->table('payroll_payslip_batches')->where('company_id', $company)->where('uuid', $key)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }$items = $this->db->table('payroll_items')->where('company_id', $company)->where('payroll_run_id', $run)->pluck('id');
            $batch = (int) $this->db->table('payroll_payslip_batches')->insertGetId(['uuid' => $key, 'company_id' => $company, 'payroll_run_id' => $run, 'status' => 'processing', 'total_count' => $items->count(), 'pending_count' => $items->count(), 'requested_by' => $actor, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($items as $item) {
                if (is_numeric($item)) {
                    $this->requestItem($company, $run, (int) $item, $actor, (string) Str::uuid());
                }
            }$generated = $this->db->table('payroll_payslips')->where('company_id', $company)->where('payroll_run_id', $run)->where('status', 'generated')->count();
            $this->db->table('payroll_payslip_batches')->where('id', $batch)->update(['status' => $generated === $items->count() ? 'completed' : 'processing', 'pending_count' => max(0, $items->count() - $generated), 'generated_count' => $generated, 'completed_at' => $generated === $items->count() ? now() : null, 'updated_at' => now()]);

            return $batch;
        }, 3);
    }

    public function generate(int $id): void
    {
        $this->db->transaction(function () use ($id): void {
            $p = $this->db->table('payroll_payslips')->where('id', $id)->lockForUpdate()->first();
            if (! is_object($p) || in_array($p->status, ['generated', 'published', 'revoked'], true)) {
                return;
            }$this->db->table('payroll_payslips')->where('id', $id)->update(['status' => 'generating', 'updated_at' => now()]);
            $s = $this->db->table('payroll_payslip_snapshots')->where('payroll_payslip_id', $id)->first();
            if (! is_object($s) || ! is_string($s->snapshot_payload)) {
                throw new \RuntimeException('Payslip snapshot missing.');
            }$decoded = json_decode($s->snapshot_payload, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                throw new \RuntimeException('Payslip snapshot invalid.');
            }
            $snapshot = new PayslipSnapshotData($this->stringKeyedArray($decoded));
            $at = now()->toIso8601String();
            $bytes = $this->renderer->render($snapshot, $this->integer($p, 'payslip_version'), $at);
            $uuid = $this->string($p, 'uuid');
            $path = 'payroll-payslips/'.$this->integer($p, 'company_id').'/'.$this->integer($p, 'payroll_period_id').'/'.$uuid.'.pdf';
            if (! Storage::disk('local')->put($path, $bytes)) {
                throw new \RuntimeException('Payslip storage failed.');
            }
            $this->db->table('payroll_payslips')->where('id', $id)->update(['status' => 'generated', 'storage_path' => $path, 'file_name' => 'payslip-'.$uuid.'.pdf', 'mime_type' => 'application/pdf', 'file_size' => strlen($bytes), 'checksum' => $this->integrity->checksum($bytes), 'generated_at' => now(), 'updated_at' => now()]);
            event(new PayslipChanged($this->integer($p, 'company_id'), $id, $this->integer($p, 'employee_id'), 'generated', 'generated'));
        });
    }

    public function publish(int $company, int $id, int $actor): void
    {
        $this->db->transaction(function () use ($company, $id, $actor): void {
            $p = $this->payslip($company, $id, true);
            $status = $this->string($p, 'status');
            if ($status === 'published') {
                return;
            }
            if ($status !== 'generated') {
                throw ValidationException::withMessages(['status' => 'Chỉ Payslip generated mới được publish.']);
            }$this->verify($p);
            $this->db->table('payroll_payslips')->where('id', $id)->update(['status' => 'published', 'published_by' => $actor, 'published_at' => now(), 'delivery_status' => 'delivered', 'last_delivered_at' => now(), 'updated_at' => now()]);
            $this->log($company, $id, $actor, 'publish');
            activity('payroll-payslip')->causedBy($actor)->event('published')->withProperties(['company_id' => $company, 'payslip_id' => $id])->log('Payslip published');
            event(new PayslipChanged($company, $id, $this->integer($p, 'employee_id'), 'published', 'published'));
        });
    }

    public function revoke(int $company, int $id, int $actor, string $reason): void
    {
        $this->db->transaction(function () use ($company, $id, $actor, $reason): void {
            $p = $this->payslip($company, $id, true);
            if ($this->string($p, 'status') === 'revoked') {
                return;
            }$this->db->table('payroll_payslips')->where('id', $id)->update(['status' => 'revoked', 'revoked_by' => $actor, 'revoked_at' => now(), 'revocation_reason' => $reason, 'updated_at' => now()]);
            $this->log($company, $id, $actor, 'revoke');
            event(new PayslipChanged($company, $id, $this->integer($p, 'employee_id'), 'revoked', 'revoked'));
        });
    }

    public function regenerate(int $company, int $id, int $actor): void
    {
        $p = $this->payslip($company, $id, true);
        $status = $this->string($p, 'status');
        if ($status === 'published' || $status === 'revoked') {
            throw ValidationException::withMessages(['status' => 'Published/revoked Payslip không được regenerate cùng version.']);
        }$this->db->table('payroll_payslips')->where('id', $id)->update(['status' => 'pending', 'generated_at' => null, 'updated_at' => now()]);
        $this->log($company, $id, $actor, 'regenerate');
        GeneratePayslipPdfJob::dispatch($id);
    }

    public function download(int $company, int $id, int $actor, bool $employeeOwner = false): StreamedResponse
    {
        $p = $this->payslip($company, $id, false);
        $status = $this->string($p, 'status');
        if ($status === 'revoked' || ($employeeOwner && $status !== 'published')) {
            abort(403);
        }$this->verify($p);
        $this->log($company, $id, $actor, 'download');
        $this->db->table('payroll_payslips')->where('id', $id)->update(['delivery_status' => 'downloaded', 'updated_at' => now()]);

        return Storage::disk('local')->download($this->string($p, 'storage_path'), $this->safeName($this->string($p, 'file_name')), ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function payslip(int $company, int $id, bool $lock): object
    {
        $q = $this->db->table('payroll_payslips')->where('company_id', $company)->where('id', $id);
        if ($lock) {
            $q->lockForUpdate();
        }$p = $q->first();
        if (! is_object($p)) {
            abort(404);
        }

        return $p;
    }

    private function verify(object $p): void
    {
        $path = $this->nullableString($p, 'storage_path');
        $checksum = $this->nullableString($p, 'checksum');
        $disk = $this->nullableString($p, 'storage_disk');
        if ($path === null || $checksum === null || $disk === null) {
            throw ValidationException::withMessages(['file' => 'PAYSLIP_FILE_INTEGRITY_FAILED']);
        }
        $bytes = Storage::disk($disk)->get($path);
        if (! is_string($bytes) || ! $this->integrity->valid($bytes, $checksum)) {
            throw ValidationException::withMessages(['file' => 'PAYSLIP_FILE_INTEGRITY_FAILED']);
        }
    }

    private function log(int $company, int $id, int $actor, string $action): void
    {
        $this->db->table('payroll_payslip_access_logs')->insert(['company_id' => $company, 'payroll_payslip_id' => $id, 'actor_type' => 'user', 'actor_id' => $actor, 'action' => $action, 'accessed_at' => now()]);
    }

    private function safeName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '-', $name) ?: 'payslip.pdf';
    }

    private function integer(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;
        if (! is_numeric($value)) {
            throw new \RuntimeException("Invalid integer column: {$key}");
        }

        return (int) $value;
    }

    private function string(object $row, string $key): string
    {
        $value = $this->nullableString($row, $key);
        if ($value === null) {
            throw new \RuntimeException("Invalid string column: {$key}");
        }

        return $value;
    }

    private function nullableString(object $row, string $key): ?string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<mixed, mixed>  $payload
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $payload): array
    {
        $normalized = [];
        foreach ($payload as $key => $value) {
            if (! is_string($key)) {
                throw new \RuntimeException('Payslip snapshot contains an invalid key.');
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
