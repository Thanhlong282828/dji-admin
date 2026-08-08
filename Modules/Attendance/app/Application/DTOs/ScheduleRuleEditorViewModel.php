<?php

namespace Modules\Attendance\Application\DTOs;

final readonly class ScheduleRuleEditorViewModel
{
    /**
     * @param  array{id:int,code:string,name:string,status:string,version:int,starts_at?:string,ends_at?:string,crosses_midnight?:bool}  $parent
     * @param  list<array<string,bool|int|string|null>>  $rules
     * @param  list<array{id:int,code:string,name:string}>  $shifts
     */
    public function __construct(public string $type, public array $parent, public array $rules, public array $shifts) {}
}
