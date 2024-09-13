<?php

namespace Dankkomcg\MySQL\Sync;

use Dankkomcg\MySQL\Sync\Mappers\LoggerInterface;
use Dankkomcg\MySQL\Sync\Mappers\DisplayConsoleLog;

abstract class Loggable
{
    private static ?LoggerInterface $logger = null;
    
    protected function logger(): LoggerInterface
    {
        if (self::$logger === null) {
            self::$logger = new DisplayConsoleLog();
        }
        return self::$logger;
    }

    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }
}