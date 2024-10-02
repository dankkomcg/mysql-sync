<?php

namespace Dankkomcg\MySQL\Sync\Exceptions;

class TableSyncException extends \Exception {

    public function __construct($message = "", $code = 0, \Throwable $previous = null) {
        parent::__construct(
            sprintf(
                "%s can't make the synchronization: %s", __CLASS__, $message
            ), $code, $previous
        );
    }

}