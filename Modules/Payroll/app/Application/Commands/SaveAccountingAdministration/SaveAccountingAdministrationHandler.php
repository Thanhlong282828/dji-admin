<?php

namespace Modules\Payroll\Application\Commands\SaveAccountingAdministration;

use Modules\Payroll\Domain\Services\AccountingAdministrationService;

final readonly class SaveAccountingAdministrationHandler
{
    public function __construct(private AccountingAdministrationService $service) {}

    public function handle(SaveAccountingAdministrationCommand $c): int
    {
        return match ($c->entity) {
            'account' => $this->service->saveAccount($c->companyId, $c->entityId, $this->account($c->data), $c->actorId),
            'policy' => $this->service->savePolicy($c->companyId, $c->entityId, $this->policy($c->data), $c->actorId),
            'mapping' => $this->service->saveMapping($c->companyId, $c->entityId, $this->mapping($c->data), $c->actorId),
            default => throw new \InvalidArgumentException('Unsupported accounting administration entity.'),
        };
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{code:string,name:string,account_type:string,normal_balance:string,currency:?string,allow_posting:bool,status:string,effective_from:string,effective_to:?string,parent_id:?int}
     */
    private function account(array $d): array
    {
        return ['code' => $this->string($d, 'code'), 'name' => $this->string($d, 'name'), 'account_type' => $this->string($d, 'account_type'), 'normal_balance' => $this->string($d, 'normal_balance'), 'currency' => $this->nullableString($d, 'currency'), 'allow_posting' => $this->boolean($d, 'allow_posting'), 'status' => $this->string($d, 'status'), 'effective_from' => $this->string($d, 'effective_from'), 'effective_to' => $this->nullableString($d, 'effective_to'), 'parent_id' => $this->nullableInteger($d, 'parent_id')];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{code:string,name:string,version:int,currency:string,aggregation_strategy:string,effective_from:string,effective_to:?string,rounding_rule:string,status:string,note:?string}
     */
    private function policy(array $d): array
    {
        return ['code' => $this->string($d, 'code'), 'name' => $this->string($d, 'name'), 'version' => $this->integer($d, 'version'), 'currency' => $this->string($d, 'currency'), 'aggregation_strategy' => $this->string($d, 'aggregation_strategy'), 'effective_from' => $this->string($d, 'effective_from'), 'effective_to' => $this->nullableString($d, 'effective_to'), 'rounding_rule' => $this->string($d, 'rounding_rule'), 'status' => $this->string($d, 'status'), 'note' => $this->nullableString($d, 'note')];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{accounting_policy_id:int,source_type:string,source_code:string,debit_account_id:?int,credit_account_id:?int,cost_center_id:?int,organization_unit_id:?int,priority:int,effective_from:string,effective_to:?string,status:string}
     */
    private function mapping(array $d): array
    {
        return ['accounting_policy_id' => $this->integer($d, 'accounting_policy_id'), 'source_type' => $this->string($d, 'source_type'), 'source_code' => $this->string($d, 'source_code'), 'debit_account_id' => $this->nullableInteger($d, 'debit_account_id'), 'credit_account_id' => $this->nullableInteger($d, 'credit_account_id'), 'cost_center_id' => $this->nullableInteger($d, 'cost_center_id'), 'organization_unit_id' => $this->nullableInteger($d, 'organization_unit_id'), 'priority' => $this->integer($d, 'priority'), 'effective_from' => $this->string($d, 'effective_from'), 'effective_to' => $this->nullableString($d, 'effective_to'), 'status' => $this->string($d, 'status')];
    }

    /** @param array<string,mixed> $d */
    private function string(array $d, string $key): string
    {
        $value = $d[$key] ?? null;
        if (! is_string($value)) {
            throw new \InvalidArgumentException('Invalid '.$key);
        }

        return $value;
    }

    /** @param array<string,mixed> $d */
    private function nullableString(array $d, string $key): ?string
    {
        $value = $d[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string,mixed> $d */
    private function integer(array $d, string $key): int
    {
        $value = $d[$key] ?? null;
        if (! is_int($value)) {
            throw new \InvalidArgumentException('Invalid '.$key);
        }

        return $value;
    }

    /** @param array<string,mixed> $d */
    private function nullableInteger(array $d, string $key): ?int
    {
        $value = $d[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /** @param array<string,mixed> $d */
    private function boolean(array $d, string $key): bool
    {
        $value = $d[$key] ?? false;

        return $value === true || $value === 1 || $value === '1';
    }
}
