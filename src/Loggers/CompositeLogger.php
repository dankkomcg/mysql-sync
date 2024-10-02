<?php

namespace Dankkomcg\MySQL\Sync\Loggers;

class CompositeLogger implements Logger
{
    private array $loggers = [];

    public function addLogger(Logger $logger): void
    {
        $this->loggers[] = $logger;
    }

    public function info(string $message): void
    {
        $this->write($message, 'info');
    }

    public function warning(string $message): void
    {
        $this->write($message, 'warning');
    }

    public function error(string $message): void
    {
        $this->write($message, 'error');
    }

    public function success(string $message): void
    {
        $this->write($message, 'success');
    }

    public function write(string $message, string $level = 'info'): void
    {
        foreach ($this->loggers as $logger) {
            $logger->write($message, $level);
        }
    }

}