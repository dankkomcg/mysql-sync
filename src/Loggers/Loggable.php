<?php

namespace Dankkomcg\MySQL\Sync\Loggers;

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