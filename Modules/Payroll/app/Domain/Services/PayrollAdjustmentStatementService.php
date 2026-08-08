<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Application\DTOs\AdjustmentStatementResult;
use Modules\Payroll\Domain\Enums\PayrollAdjustmentDownstreamStatus;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class PayrollAdjustmentStatementService
{
    public function __construct(private ConnectionInterface $db, private AdjustmentStatementPdfRenderer $renderer, private PayrollAdjustmentIntegrityService $integrity) {}

    public function generate(int $companyId, int $adjustmentId, int $actorId): AdjustmentStatementResult
    {
        $context = $this->context($companyId, $adjustmentId, true);
        $intentId = $this->i($context['intent'], 'id');
        $statement = $context['statement'];
        $path = $this->n($statement, 'storage_path');
        $disk = $this->n($statement, 'storage_disk');
        $checksum = $this->n($statement, 'checksum');
        $size = $this->ni($statement, 'file_size');
        if ($path !== null && $disk !== null && $checksum !== null && $size !== null && Storage::disk($disk)->exists($path)) {
            $bytes = Storage::disk($disk)->get($path);
            if (is_string($bytes) && $this->integrity->valid($bytes, $checksum, $size)) {
                return new AdjustmentStatementResult($intentId, PayrollAdjustmentDownstreamStatus::Completed, $this->i($statement, 'id'), $this->n($statement, 'file_name'), $size, true);
            }
        }
        $view = $this->viewData($context);
        $bytes = $this->renderer->render($view);
        $uuid = $this->s($statement, 'uuid');
        $path = 'payroll-adjustments/'.$companyId.'/'.$adjustmentId.'/'.$uuid.'.pdf';
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw new \RuntimeException('ADJUSTMENT_STATEMENT_STORAGE_FAILED');
        }
        $checksum = $this->integrity->checksum($bytes);
        $size = strlen($bytes);
        $this->db->transaction(function () use ($companyId, $adjustmentId, $actorId, $statement, $path, $checksum, $size): void {
            $locked = $this->db->table('payroll_adjustment_payslips')->where('company_id', $companyId)->where('id', $this->i($statement, 'id'))->lockForUpdate()->first();
            if (! is_object($locked)) {
                throw new \RuntimeException('Adjustment Statement disappeared.');
            }
            $this->db->table('payroll_adjustment_payslips')->where('id', $this->i($statement, 'id'))->update([
                'storage_disk' => 'local', 'storage_path' => $path, 'file_name' => 'adjustment-statement-'.$this->s($statement, 'uuid').'.pdf',
                'mime_type' => 'application/pdf', 'file_size' => $size, 'checksum' => $checksum,
                'snapshot_checksum' => $this->s($statement, 'payload_checksum'), 'generation_version' => max(1, $this->i($statement, 'generation_version')),
                'generated_by' => $actorId, 'generated_at' => now(), 'status' => 'generated', 'updated_at' => now(),
            ]);
            activity('payroll-adjustment')->causedBy($actorId)->event('statement_generated')->withProperties(['company_id' => $companyId, 'adjustment_id' => $adjustmentId, 'statement_id' => $this->i($statement, 'id')])->log('Adjustment Statement generated');
        }, 3);

        return new AdjustmentStatementResult($intentId, PayrollAdjustmentDownstreamStatus::Completed, $this->i($statement, 'id'), 'adjustment-statement-'.$uuid.'.pdf', $size, false);
    }

    public function download(int $companyId, int $adjustmentId, int $actorId): StreamedResponse
    {
        $context = $this->context($companyId, $adjustmentId, false);
        $statement = $context['statement'];
        $disk = $this->n($statement, 'storage_disk');
        $path = $this->n($statement, 'storage_path');
        $checksum = $this->n($statement, 'checksum');
        $size = $this->ni($statement, 'file_size');
        if ($disk === null || $path === null || $checksum === null || $size === null || $this->s($statement, 'mime_type') !== 'application/pdf' || ! Storage::disk($disk)->exists($path)) {
            throw ValidationException::withMessages(['statement' => 'ADJUSTMENT_STATEMENT_MISSING']);
        }
        $bytes = Storage::disk($disk)->get($path);
        if (! is_string($bytes) || ! $this->integrity->valid($bytes, $checksum, $size) || ! str_starts_with($bytes, '%PDF-')) {
            throw ValidationException::withMessages(['statement' => 'ADJUSTMENT_STATEMENT_INTEGRITY_FAILED']);
        }
        $this->db->table('payroll_adjustment_access_logs')->insert(['company_id' => $companyId, 'adjustment_request_id' => $adjustmentId, 'actor_id' => $actorId, 'action' => 'statement_download', 'accessed_at' => now()]);

        return Storage::disk($disk)->download($path, $this->safeName($this->s($statement, 'file_name')), ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array{adjustment:object,statement:object,intent:object,history:list<array<string,mixed>>,historical:array<string,mixed>,correction:array<string,mixed>,components:list<array<string,mixed>>,recovery:object|null} */
    private function context(int $companyId, int $adjustmentId, bool $lock): array
    {
        $query = $this->db->table('payroll_adjustment_requests as a')->join('payroll_adjustment_reasons as r', 'r.id', '=', 'a.adjustment_reason_id')->join('payroll_adjustment_runs as ar', 'ar.adjustment_request_id', '=', 'a.id')->where('a.company_id', $companyId)->where('a.id', $adjustmentId)->where('a.status', 'applied')->select(['a.*', 'r.code as reason_code', 'r.name as reason_name', 'ar.id as adjustment_run_id', 'ar.delta_gross_minor', 'ar.delta_employee_insurance_minor', 'ar.delta_employer_insurance_minor', 'ar.delta_personal_income_tax_minor', 'ar.delta_net_minor', 'ar.currency', 'ar.delta_calculation_version']);
        if ($lock) {
            $query->lockForUpdate();
        }
        $adjustment = $query->first();
        if (! is_object($adjustment)) {
            abort(404);
        }
        $statement = $this->db->table('payroll_adjustment_payslips')->where('company_id', $companyId)->where('adjustment_request_id', $adjustmentId)->first();
        $intent = $this->db->table('payroll_adjustment_downstream_intents')->where('company_id', $companyId)->where('adjustment_request_id', $adjustmentId)->where('intent_type', 'statement')->first();
        if (! is_object($statement) || ! is_object($intent)) {
            throw ValidationException::withMessages(['statement' => 'ADJUSTMENT_STATEMENT_INTENT_MISSING']);
        }
        $snapshots = $this->db->table('payroll_adjustment_snapshots')->where('adjustment_request_id', $adjustmentId)->pluck('snapshot_payload', 'snapshot_type');
        $historical = $this->json($snapshots->get('historical'));
        $correction = $this->json($snapshots->get('correction'));
        $components = [];
        foreach ($this->db->table('payroll_adjustment_components')->where('adjustment_request_id', $adjustmentId)->orderBy('id')->get() as $row) {
            $components[] = $this->objectArray($row);
        }
        $history = [];
        foreach ($this->db->table('payroll_adjustment_histories')->where('adjustment_request_id', $adjustmentId)->orderBy('id')->get() as $row) {
            $history[] = $this->objectArray($row);
        }
        $recovery = $this->db->table('payroll_adjustment_recoveries')->where('adjustment_request_id', $adjustmentId)->first();

        return ['adjustment' => $adjustment, 'statement' => $statement, 'intent' => $intent, 'history' => $history, 'historical' => $historical, 'correction' => $correction, 'components' => $components, 'recovery' => is_object($recovery) ? $recovery : null];
    }

    /**
     * @param  array{adjustment:object,statement:object,intent:object,history:list<array<string,mixed>>,historical:array<string,mixed>,correction:array<string,mixed>,components:list<array<string,mixed>>,recovery:object|null}  $context
     * @return array<string, mixed>
     */
    private function viewData(array $context): array
    {
        return ['adjustment' => (array) $context['adjustment'], 'statement' => (array) $context['statement'], 'historical' => $context['historical'], 'correction' => $context['correction'], 'components' => $context['components'], 'history' => $context['history'], 'recovery' => $context['recovery'] === null ? null : (array) $context['recovery'], 'generated_at' => now()->toIso8601String()];
    }

    /** @return array<string,mixed> */
    private function json(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $key => $item) {
            if (is_string($key)) {
                $out[$key] = $item;
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function objectArray(object $row): array
    {
        $result = [];
        foreach (get_object_vars($row) as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function safeName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '-', $name) ?: 'adjustment-statement.pdf';
    }

    private function numeric(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function i(object $row, string $key): int
    {
        return $this->numeric($row->{$key} ?? null) ?? 0;
    }

    private function ni(object $row, string $key): ?int
    {
        return $this->numeric($row->{$key} ?? null);
    }

    private function s(object $row, string $key): string
    {
        return $this->n($row, $key) ?? '';
    }

    private function n(object $row, string $key): ?string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : null;
    }
}
