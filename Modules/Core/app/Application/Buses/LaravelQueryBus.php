<?php

namespace Modules\Core\Application\Buses;

use Illuminate\Contracts\Container\Container;
use LogicException;

final readonly class LaravelQueryBus implements QueryBusInterface
{
    public function __construct(private Container $container) {}

    public function ask(object $query): mixed
    {
        $messageClass = $query::class;
        $handler = preg_replace('/Query$/', 'Handler', $messageClass);

        if (! is_string($handler) || ! class_exists($handler)) {
            throw new LogicException("No query handler registered for {$messageClass}");
        }

        $resolved = $this->container->make($handler);
        if (! is_object($resolved) || ! method_exists($resolved, 'handle')) {
            throw new LogicException("Query handler {$handler} must define handle().");
        }

        return $resolved->handle($query);
    }
}
