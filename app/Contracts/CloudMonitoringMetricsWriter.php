<?php

namespace App\Contracts;

interface CloudMonitoringMetricsWriter
{
    /** @param list<array{type:string,value:int|float,kind:'int64'|'double'}> $metrics */
    public function export(array $metrics): void;
}
