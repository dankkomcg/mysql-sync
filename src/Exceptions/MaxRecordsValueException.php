<?php

namespace Dankkomcg\MySQL\Sync\Exceptions;

class MaxRecordsValueException extends \Exception {

    public function __construct($message = "", $code = 0, \Throwable $previous = null) {
        parent::__construct(
            sprintf(
                "%s can't define the max record value: %s", __CLASS__, $message
            ), $code, $previous
        );
    }

}