<?php

namespace Dankkomcg\MySQL\Sync\Mappers;

interface LoggerInterface
{
    public function write(string $message, string $level = 'info'): void;
    public function info(string $message): void;
    public function warning(string $message): void;
    public function error(string $message): void;
    public function success(string $message): void;
}