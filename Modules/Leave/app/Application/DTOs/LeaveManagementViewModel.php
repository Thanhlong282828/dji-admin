<?php

namespace Modules\Leave\Application\DTOs;

final readonly class LeaveManagementViewModel
{
    /**
     * @param  list<array<string,int|string|null>>  $policies
     * @param  list<array<string,int|string|null>>  $assignments
     * @param  list<array<string,int|string|null>>  $balances
     * @param  list<array{id:int,employee_code:string,name:string}>  $employees
     * @param  list<array{id:int,code:string,name:string}>  $types
     */
    public function __construct(public array $policies, public array $assignments, public array $balances, public array $employees, public array $types) {}

    public function toJson(): string
    {
        return json_encode([
            'policies' => $this->policies,
            'assignments' => $this->assignments,
            'balances' => $this->balances,
            'employees' => $this->employees,
            'types' => $this->types,
        ], JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new \UnexpectedValueException('Invalid leave management cache payload.');
        }

        return new self(
            self::scalarRows($data['policies'] ?? null),
            self::scalarRows($data['assignments'] ?? null),
            self::scalarRows($data['balances'] ?? null),
            self::employees($data['employees'] ?? null),
            self::types($data['types'] ?? null),
        );
    }

    /** @return list<array<string, int|string|null>> */
    private static function scalarRows(mixed $value): array
    {
        if (! is_array($value)) {
            throw new \UnexpectedValueException('Invalid leave row cache payload.');
        }

        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                throw new \UnexpectedValueException('Invalid leave row cache item.');
            }
            $normalized = [];
            foreach ($row as $key => $item) {
                if (! is_string($key) || (! is_int($item) && ! is_string($item) && $item !== null)) {
                    throw new \UnexpectedValueException('Invalid leave row cache value.');
                }
                $normalized[$key] = $item;
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    /** @return list<array{id:int, employee_code:string, name:string}> */
    private static function employees(mixed $value): array
    {
        if (! is_array($value)) {
            throw new \UnexpectedValueException('Invalid leave employee cache payload.');
        }

        $employees = [];
        foreach ($value as $item) {
            if (! is_array($item) || ! is_int($item['id'] ?? null) || ! is_string($item['employee_code'] ?? null) || ! is_string($item['name'] ?? null)) {
                throw new \UnexpectedValueException('Invalid leave employee cache item.');
            }
            $employees[] = ['id' => $item['id'], 'employee_code' => $item['employee_code'], 'name' => $item['name']];
        }

        return $employees;
    }

    /** @return list<array{id:int, code:string, name:string}> */
    private static function types(mixed $value): array
    {
        if (! is_array($value)) {
            throw new \UnexpectedValueException('Invalid leave type cache payload.');
        }

        $types = [];
        foreach ($value as $item) {
            if (! is_array($item) || ! is_int($item['id'] ?? null) || ! is_string($item['code'] ?? null) || ! is_string($item['name'] ?? null)) {
                throw new \UnexpectedValueException('Invalid leave type cache item.');
            }
            $types[] = ['id' => $item['id'], 'code' => $item['code'], 'name' => $item['name']];
        }

        return $types;
    }
}
