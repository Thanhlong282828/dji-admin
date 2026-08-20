<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class PayrollDeliveryViewModel
{
    /**
     * @param  list<array{id:int,code:string,name:string,category:string,calculation_type:string,currency_code:string|null,status:string,version:int}>  $components
     * @param  list<array{id:int,employee_id:int,employee_name:string,component_name:string,effective_from:string,effective_to:string|null,status:string,version:int}>  $assignments
     * @param  list<array{id:int,run_number:string,status:string,currency_code:string,employee_count:int,calculation_version:int,version:int}>  $runs
     */
    public function __construct(public array $components, public array $assignments, public array $runs) {}

    public function toJson(): string
    {
        return json_encode(['components' => $this->components, 'assignments' => $this->assignments, 'runs' => $this->runs], JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new \UnexpectedValueException('Invalid payroll delivery cache payload.');
        }

        return new self(
            self::components($data['components'] ?? null),
            self::assignments($data['assignments'] ?? null),
            self::runs($data['runs'] ?? null),
        );
    }

    /** @return list<array{id:int,code:string,name:string,category:string,calculation_type:string,currency_code:string|null,status:string,version:int}> */
    private static function components(mixed $value): array
    {
        if (! is_array($value)) {
            throw new \UnexpectedValueException('Invalid payroll component cache payload.');
        }
        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                throw new \UnexpectedValueException('Invalid payroll component cache item.');
            }
            $rows[] = [
                'id' => self::integer($row, 'id'), 'code' => self::text($row, 'code'), 'name' => self::text($row, 'name'),
                'category' => self::text($row, 'category'), 'calculation_type' => self::text($row, 'calculation_type'),
                'currency_code' => self::nullableText($row, 'currency_code'), 'status' => self::text($row, 'status'), 'version' => self::integer($row, 'version'),
            ];
        }

        return $rows;
    }

    /** @return list<array{id:int,employee_id:int,employee_name:string,component_name:string,effective_from:string,effective_to:string|null,status:string,version:int}> */
    private static function assignments(mixed $value): array
    {
        if (! is_array($value)) {
            throw new \UnexpectedValueException('Invalid payroll assignment cache payload.');
        }
        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                throw new \UnexpectedValueException('Invalid payroll assignment cache item.');
            }
            $rows[] = [
                'id' => self::integer($row, 'id'), 'employee_id' => self::integer($row, 'employee_id'),
                'employee_name' => self::text($row, 'employee_name'), 'component_name' => self::text($row, 'component_name'),
                'effective_from' => self::text($row, 'effective_from'), 'effective_to' => self::nullableText($row, 'effective_to'),
                'status' => self::text($row, 'status'), 'version' => self::integer($row, 'version'),
            ];
        }

        return $rows;
    }

    /** @return list<array{id:int,run_number:string,status:string,currency_code:string,employee_count:int,calculation_version:int,version:int}> */
    private static function runs(mixed $value): array
    {
        if (! is_array($value)) {
            throw new \UnexpectedValueException('Invalid payroll run cache payload.');
        }
        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                throw new \UnexpectedValueException('Invalid payroll run cache item.');
            }
            $rows[] = [
                'id' => self::integer($row, 'id'), 'run_number' => self::text($row, 'run_number'), 'status' => self::text($row, 'status'),
                'currency_code' => self::text($row, 'currency_code'), 'employee_count' => self::integer($row, 'employee_count'),
                'calculation_version' => self::integer($row, 'calculation_version'), 'version' => self::integer($row, 'version'),
            ];
        }

        return $rows;
    }

    /** @param array<mixed> $row */
    private static function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (! is_int($value)) {
            throw new \UnexpectedValueException("Invalid integer field {$key} in payroll delivery cache.");
        }

        return $value;
    }

    /** @param array<mixed> $row */
    private static function text(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (! is_string($value)) {
            throw new \UnexpectedValueException("Invalid string field {$key} in payroll delivery cache.");
        }

        return $value;
    }

    /** @param array<mixed> $row */
    private static function nullableText(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if ($value !== null && ! is_string($value)) {
            throw new \UnexpectedValueException("Invalid nullable string field {$key} in payroll delivery cache.");
        }

        return $value;
    }
}
