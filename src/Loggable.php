<?php

namespace Dankkomcg\MySQL\Sync;

use Dankkomcg\MySQL\Sync\Loggers\ConsoleLogger;
use Dankkomcg\MySQL\Sync\Loggers\Logger;

trait Loggable {

    private static ?Logger $logger = null;
    
    protected function logger(): Logger
    {
        if (self::$logger === null) {
            self::$logger = new ConsoleLogger();
        }
        return self::$logger;
    }

    public function setLogger(Logger $logger): void
    {
        self::$logger = $logger;
    }
}