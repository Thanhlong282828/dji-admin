<?php

namespace Modules\Core\Application\Buses;

use Illuminate\Contracts\Container\Container;
use LogicException;

final readonly class LaravelCommandBus implements CommandBusInterface
{
    public function __construct(private Container $container) {}

    public function dispatch(object $command): mixed
    {
        $messageClass = $command::class;
        $handler = preg_replace('/Command$/', 'Handler', $messageClass);

        if (! is_string($handler) || ! class_exists($handler)) {
            throw new LogicException("No command handler registered for {$messageClass}");
        }

        return $this->container->make($handler)->handle($command);
    }
}
