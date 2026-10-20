<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Application\DTOs\ErpAdministrationData;

final readonly class ErpAdministrationService
{
    public function __construct(private ConnectionInterface $db, private StringEncrypter $encrypter) {}

    public function execute(ErpAdministrationData $data): int
    {
        return $this->db->transaction(fn (): int => match ($data->entity) {
            'connection' => $this->connection($data),
            'mapping' => $this->mapping($data),
            default => throw new \InvalidArgumentException('Unsupported ERP administration entity.'),
        }, 3);
    }

    private function connection(ErpAdministrationData $data): int
    {
        $values = ['name' => $this->required($data, 'name'), 'provider' => $this->required($data, 'provider'), 'endpoint_reference' => $this->nullable($data, 'endpoint_reference'), 'status' => $this->required($data, 'status'), 'updated_by' => $data->actorId, 'updated_at' => now()];
        $credential = $this->nullable($data, 'credential_reference');
        if ($credential !== null) {
            $values['credential_reference'] = $this->encrypter->encryptString($credential);
        }
        if ($data->action === 'create') {
            $values += ['uuid' => (string) Str::uuid(), 'company_id' => $data->companyId, 'created_by' => $data->actorId, 'created_at' => now()];

            return $this->db->table('erp_connections')->insertGetId($values);
        }
        $id = $data->id ?? 0;
        $updated = $this->db->table('erp_connections')->where('company_id', $data->companyId)->where('id', $id)->lockForUpdate()->update($values);
        if ($updated !== 1) {
            abort(404);
        }

        return $id;
    }

    private function mapping(ErpAdministrationData $data): int
    {
        $connectionId = $this->integer($data, 'erp_connection_id');
        $connection = $this->db->table('erp_connections')->where('company_id', $data->companyId)->where('id', $connectionId)->lockForUpdate()->exists();
        if (! $connection) {
            abort(404);
        }
        $source = $this->required($data, 'source_account_code');
        $account = $this->db->table('accounting_accounts')->where('company_id', $data->companyId)->where('code', $source)->exists();
        if (! $account) {
            throw ValidationException::withMessages(['source_account_code' => 'Accounting Account không thuộc Company hoặc không tồn tại.']);
        }
        $effectiveFrom = $this->required($data, 'effective_from');
        $effectiveTo = $this->nullable($data, 'effective_to');
        if ($effectiveTo !== null && $effectiveTo < $effectiveFrom) {
            throw ValidationException::withMessages(['effective_to' => 'Effective To phải sau hoặc bằng Effective From.']);
        }
        $previous = null;
        if ($data->action === 'update') {
            $previous = $this->db->table('erp_mapping_rules')->where('company_id', $data->companyId)->where('id', $data->id ?? 0)->lockForUpdate()->first();
            if (! is_object($previous)) {
                abort(404);
            }
            $this->db->table('erp_mapping_rules')->where('id', $data->id)->whereNull('effective_to')->update(['effective_to' => now()->subDay()->toDateString(), 'status' => 'superseded', 'updated_by' => $data->actorId, 'updated_at' => now()]);
        }
        $version = is_object($previous) ? $this->objectInt($previous, 'version') + 1 : 1;
        $id = $this->db->table('erp_mapping_rules')->insertGetId(['company_id' => $data->companyId, 'erp_connection_id' => $connectionId, 'source_account_code' => $source, 'external_account_code' => $this->required($data, 'external_account_code'), 'version' => $version, 'effective_from' => $effectiveFrom, 'effective_to' => $effectiveTo, 'dimension_mapping' => null, 'status' => $this->required($data, 'status'), 'created_by' => $data->actorId, 'updated_by' => $data->actorId, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function required(ErpAdministrationData $data, string $key): string
    {
        $value = $data->attributes[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : throw new \InvalidArgumentException('Missing '.$key);
    }

    private function nullable(ErpAdministrationData $data, string $key): ?string
    {
        $value = $data->attributes[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function integer(ErpAdministrationData $data, string $key): int
    {
        $value = $data->attributes[$key] ?? null;

        return is_numeric($value) ? (int) $value : throw new \InvalidArgumentException('Invalid '.$key);
    }

    private function objectInt(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }
}
