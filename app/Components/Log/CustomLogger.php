<?php

namespace App\Components\Log;


use App\Components\Log\Formatter\JsonFormatter;

class CustomLogger
{
    /**
     * Create a custom Monolog instance.
     *
     * @param  array $config
     * @return \Monolog\Logger
     * @throws \Exception
     */
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {

            $formatter = new JsonFormatter();
            $handler->setFormatter($formatter);
        }
    }
}