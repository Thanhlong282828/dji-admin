<?php

namespace Modules\Core\Application\Buses;

interface QueryBusInterface
{
    public function ask(object $query): mixed;
}
