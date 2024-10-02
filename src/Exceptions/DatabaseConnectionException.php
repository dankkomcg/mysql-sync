<?php

namespace Dankkomcg\MySQL\Sync\Exceptions;

class DatabaseConnectionException extends \Exception {

    public function __construct($message = "", $code = 0, \Throwable $previous = null) {
        parent::__construct(
            sprintf(
                "%s establishing the database connection: %s", __CLASS__, $message
            ), $code, $previous
        );
    }

}