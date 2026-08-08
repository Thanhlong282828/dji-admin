<?php

namespace Modules\Core\Application\Buses;

interface CommandBusInterface
{
    public function dispatch(object $command): mixed;
}
