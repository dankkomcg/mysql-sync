<?php

namespace Dankkomcg\MySQL\Sync\Loggers;

use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;

class FileLogger implements Logger
{
    private MonologLogger $streamFileLogger;

    public function __construct(string $filename, string $fileLoggerName = 'sync') {

        $this->streamFileLogger     = new MonologLogger($fileLoggerName);

        $this->streamFileLogger->pushHandler(
            new StreamHandler(
                $filename, MonologLogger::DEBUG
            )
        );

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

    public function debug(string $message): void
    {
        $this->write($message);
    }

    public function write(string $message, string $level = 'info'): void
    {
        $logLevel = $this->mapLevelToMonolog($level);
        $this->streamFileLogger->log($logLevel, "[$level] $message");
    }

    /**
     * Map the library level logs names with the monolog levels identifiers
     *
     * @param string $level
     * @return int
     */
    private function mapLevelToMonolog(string $level): int {

        $map = [
            'info' => MonologLogger::INFO,
            'success' => MonologLogger::INFO,
            'warning' => MonologLogger::WARNING,
            'error' => MonologLogger::ERROR,
        ];

        return $map[$level] ?? MonologLogger::INFO;
    }
}