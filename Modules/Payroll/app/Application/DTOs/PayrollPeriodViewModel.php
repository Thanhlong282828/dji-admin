<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class PayrollPeriodViewModel
{
    /**
     * @param  list<array{id:int,code:string,name:string,start:string,end:string,pay_date:string,status:string,version:int,snapshot_count:int,run_count:int,created_at:string}>  $items
     * @param  array{id:int,code:string,name:string,start:string,end:string,pay_date:string,cutoff_at:string,status:string,version:int,note:string|null,snapshot_count:int,run_count:int,latest_snapshot_id:int|null,latest_run_id:int|null,created_at:string}|null  $detail
     * @param  list<array{id:int,code:string,name:string,start:string,end:string,status:string,version:int,latest_snapshot_version:int}>  $options
     * @param  list<array{action:string,from_status:string|null,to_status:string,actor:string,version:int,created_at:string}>  $history
     */
    public function __construct(
        public array $items = [],
        public ?array $detail = null,
        public array $options = [],
        public array $history = [],
        public int $page = 1,
        public bool $hasMore = false,
    ) {}
}
