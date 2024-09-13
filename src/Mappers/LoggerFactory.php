<?php

namespace Dankkomcg\MySQL\Sync\Mappers;

class LoggerFactory
{
    private static ?LoggerInterface $instance = null;

    public static function getInstance(): LoggerInterface
    {
        if (self::$instance === null) {
            self::$instance = self::createDefaultLogger();
        }
        return self::$instance;
    }

    public static function setInstance(LoggerInterface $logger): void
    {
        self::$instance = $logger;
    }

    private static function createDefaultLogger(): LoggerInterface
    {
        $compositeLogger = new CompositeLogger();
        $compositeLogger->addLogger(new DisplayConsoleLog());
        return $compositeLogger;
    }
}