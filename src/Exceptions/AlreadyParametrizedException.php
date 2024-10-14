<?php

namespace Dankkomcg\MySQL\Sync\Exceptions;

class AlreadyParametrizedException extends \Exception {

    public function __construct($message = "", $code = 0, \Throwable $previous = null) {
        parent::__construct(
            sprintf(
                "%s can't set the parametrization value: %s", __CLASS__, $message
            ), $code, $previous
        );
    }

}