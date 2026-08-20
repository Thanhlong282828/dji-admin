<?php

namespace Modules\Attendance\Application\DTOs;

final readonly class OvertimeManagementViewModel
{
    /**
     * @param  list<array<string, bool|int|string|null>>  $policies
     * @param  list<array<string, int|string|null>>  $assignments
     * @param  list<array<string, int|string|null>>  $requests
     * @param  list<array{id:int,employee_code:string,name:string}>  $employees
     */
    public function __construct(public array $policies, public array $assignments, public array $requests, public array $employees) {}

    public function toJson(): string
    {
        return json_encode(['policies' => $this->policies, 'assignments' => $this->assignments, 'requests' => $this->requests, 'employees' => $this->employees], JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new \UnexpectedValueException('Invalid overtime management cache payload.');
        }

        return new self(
            self::scalarRows($data['policies'] ?? null, true),
            self::textRows($data['assignments'] ?? null),
            self::textRows($data['requests'] ?? null),
            self::employees($data['employees'] ?? null),
        );
    }

    /** @return list<array<string, bool|int|string|null>> */
    private static function scalarRows(mixed $value, bool $allowBoolean): array
    {
        if (! is_array($value)) {
            throw new \UnexpectedValueException('Invalid overtime row cache payload.');
        }
        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                throw new \UnexpectedValueException('Invalid overtime row cache item.');
            }
            $normalized = [];
            foreach ($row as $key => $item) {
                if (! is_string($key) || (! is_int($item) && ! is_string($item) && $item !== null && (! $allowBoolean || ! is_bool($item)))) {
                    throw new \UnexpectedValueException('Invalid overtime row cache value.');
                }
                $normalized[$key] = $item;
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    /** @return list<array<string, int|string|null>> */
    private static function textRows(mixed $value): array
    {
        if (! is_array($value)) {
            throw new \UnexpectedValueException('Invalid overtime text row cache payload.');
        }
        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                throw new \UnexpectedValueException('Invalid overtime text row cache item.');
            }
            $normalized = [];
            foreach ($row as $key => $item) {
                if (! is_string($key) || (! is_int($item) && ! is_string($item) && $item !== null)) {
                    throw new \UnexpectedValueException('Invalid overtime text row cache value.');
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
            throw new \UnexpectedValueException('Invalid overtime employee cache payload.');
        }
        $employees = [];
        foreach ($value as $item) {
            if (! is_array($item) || ! is_int($item['id'] ?? null) || ! is_string($item['employee_code'] ?? null) || ! is_string($item['name'] ?? null)) {
                throw new \UnexpectedValueException('Invalid overtime employee cache item.');
            }
            $employees[] = ['id' => $item['id'], 'employee_code' => $item['employee_code'], 'name' => $item['name']];
        }

        return $employees;
    }
}
