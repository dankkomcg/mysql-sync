<?php

namespace Dankkomcg\MySQL\Sync\Loggers;

interface Logger
{

    /**
     * @param string $message
     * @param string $level
     * @return void
     */
    public function write(string $message, string $level = 'info'): void;

    /**
     * @param string $message
     * @return void
     */
    public function info(string $message): void;

    /**
     * @param string $message
     * @return void
     */
    public function warning(string $message): void;

    /**
     * @param string $message
     * @return void
     */
    public function error(string $message): void;

    /**
     * @param string $message
     * @return void
     */
    public function success(string $message): void;

    /**
     * @param string $message
     * @return void
     */
    public function debug(string $message): void;
}