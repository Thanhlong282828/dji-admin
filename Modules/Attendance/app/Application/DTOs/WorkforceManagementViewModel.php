<?php

namespace Modules\Attendance\Application\DTOs;

final readonly class WorkforceManagementViewModel
{
    /**
     * @param  list<array<string, bool|int|string|null>>  $rows
     * @param  list<array{id:int, code:string, name:string}>  $calendars
     * @param  list<array{id:int, code:string, name:string}>  $shifts
     * @param  list<array{id:int, employee_code:string, name:string}>  $employees
     */
    public function __construct(
        public string $section,
        public array $rows,
        public array $calendars,
        public array $shifts,
        public array $employees,
    ) {}

    public function toJson(): string
    {
        return json_encode([
            'section' => $this->section,
            'rows' => $this->rows,
            'calendars' => $this->calendars,
            'shifts' => $this->shifts,
            'employees' => $this->employees,
        ], JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data) || ! is_string($data['section'] ?? null)) {
            throw new \UnexpectedValueException('Invalid workforce management cache payload.');
        }

        return new self(
            $data['section'],
            self::scalarRows($data['rows'] ?? null),
            self::references($data['calendars'] ?? null),
            self::references($data['shifts'] ?? null),
            self::employees($data['employees'] ?? null),
        );
    }

    /** @return list<array<string, bool|int|string|null>> */
    private static function scalarRows(mixed $value): array
    {
        if (! is_array($value)) {
            throw new \UnexpectedValueException('Invalid workforce row cache payload.');
        }
        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                throw new \UnexpectedValueException('Invalid workforce row cache item.');
            }
            $normalized = [];
            foreach ($row as $key => $item) {
                if (! is_string($key) || (! is_bool($item) && ! is_int($item) && ! is_string($item) && $item !== null)) {
                    throw new \UnexpectedValueException('Invalid workforce row cache value.');
                }
                $normalized[$key] = $item;
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    /** @return list<array{id:int, code:string, name:string}> */
    private static function references(mixed $value): array
    {
        if (! is_array($value)) {
            throw new \UnexpectedValueException('Invalid workforce reference cache payload.');
        }
        $references = [];
        foreach ($value as $item) {
            if (! is_array($item) || ! is_int($item['id'] ?? null) || ! is_string($item['code'] ?? null) || ! is_string($item['name'] ?? null)) {
                throw new \UnexpectedValueException('Invalid workforce reference cache item.');
            }
            $references[] = ['id' => $item['id'], 'code' => $item['code'], 'name' => $item['name']];
        }

        return $references;
    }

    /** @return list<array{id:int, employee_code:string, name:string}> */
    private static function employees(mixed $value): array
    {
        if (! is_array($value)) {
            throw new \UnexpectedValueException('Invalid workforce employee cache payload.');
        }
        $employees = [];
        foreach ($value as $item) {
            if (! is_array($item) || ! is_int($item['id'] ?? null) || ! is_string($item['employee_code'] ?? null) || ! is_string($item['name'] ?? null)) {
                throw new \UnexpectedValueException('Invalid workforce employee cache item.');
            }
            $employees[] = ['id' => $item['id'], 'employee_code' => $item['employee_code'], 'name' => $item['name']];
        }

        return $employees;
    }
}
