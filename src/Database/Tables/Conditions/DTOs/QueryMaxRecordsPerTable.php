<?php

namespace Dankkomcg\MySQL\Sync\Database\Tables\Conditions\DTOs;

use Dankkomcg\MySQL\Sync\Exceptions\MaxRecordsValueException;

class QueryMaxRecordsPerTable {

    /**
     * @var int
     */
    private int $maxRecordsPerTable;

    /**
     * @throws MaxRecordsValueException
     */
    public function __construct(QueryChunkSize $chunkSize, int $maxRecordsPerTable = null) {
        if ($maxRecordsPerTable !== null) {
            $this->setMaxRecordsPerTable($maxRecordsPerTable, $chunkSize);
        }
    }

    /**
     * @throws MaxRecordsValueException
     */
    private function setMaxRecordsPerTable(int $maxRecordsPerTable, QueryChunkSize $chunkSize): void {

        if ($maxRecordsPerTable < $chunkSize->getChunkSizeValue()) {
            throw new MaxRecordsValueException(
                sprintf(
                    "you cant define a max records (%s) less than the chunk size (%s)",
                    $maxRecordsPerTable, $chunkSize->getChunkSizeValue()
                )
            );
        }

        $this->maxRecordsPerTable = $maxRecordsPerTable;
    }

    public function getMaxRecordsPerTableValue(): int {
        return $this->maxRecordsPerTable;
    }

}