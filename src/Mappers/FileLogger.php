<?php

namespace Dankkomcg\MySQL\Sync\Mappers;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class FileLogger implements LoggerInterface
{
    private Logger $logger;

    public function __construct(string $filename)
    {
        $this->logger = new Logger('sync');
        $this->logger->pushHandler(new StreamHandler($filename, Logger::DEBUG));
    }

    public function write(string $message, string $level = 'info'): void
    {
        $logLevel = $this->mapLevelToMonolog($level);
        $this->logger->log($logLevel, "[$level] $message");
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

    private function mapLevelToMonolog(string $level): int
    {
        $map = [
            'info' => Logger::INFO,
            'success' => Logger::INFO,
            'warning' => Logger::WARNING,
            'error' => Logger::ERROR,
        ];
        return $map[$level] ?? Logger::INFO;
    }
}