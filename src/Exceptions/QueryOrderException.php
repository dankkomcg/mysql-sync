<?php

namespace Dankkomcg\MySQL\Sync\Exceptions;

class QueryOrderException extends \Exception {

    public function __construct($message = "", $code = 0, \Throwable $previous = null) {
        parent::__construct(
            sprintf(
                "%s can't define the synchronize order: %s", __CLASS__, $message
            ), $code, $previous
        );
    }

}