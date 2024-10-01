<?php

namespace Dankkomcg\MySQL\Sync\Exceptions;

class ChunkSizeValueException extends \Exception {

    public function __construct($message = "", $code = 0, \Throwable $previous = null) {
        parent::__construct(
            sprintf(
                "%s can't define the chunk size: %s", __CLASS__, $message
            ), $code, $previous
        );
    }

}