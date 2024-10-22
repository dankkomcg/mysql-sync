<?php

declare(strict_types = 1);

namespace Dankkomcg\MySQL\Sync;

use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\DTOs\QueryChunkSize;
use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\DTOs\QueryMaxRecordsPerTable;
use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\DTOs\QueryFilteredTables;
use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\DTOs\QueryOrder;
use Dankkomcg\MySQL\Sync\Exceptions\ChunkSizeValueException;
use Dankkomcg\MySQL\Sync\Exceptions\MaxRecordsValueException;
use Dankkomcg\MySQL\Sync\Exceptions\QueryOrderException;

class SyncParameters {

    /**
     * @var QueryChunkSize
     */
    private QueryChunkSize $chunkSize;

    /**
     * @var QueryMaxRecordsPerTable
     */
    private QueryMaxRecordsPerTable $maxRecordsPerTable;

    /**
     * @var QueryOrder
     */
    private QueryOrder $queryOrder;

    /**
     * @var QueryFilteredTables
     */
    private QueryFilteredTables $filteredTables;

    /**
     * @param int $chunkSize
     * @param string $queryOrder
     * @param array $filteredTables
     * @param ?int $maxRecordsPerTable
     * @throws ChunkSizeValueException
     * @throws MaxRecordsValueException
     * @throws QueryOrderException
     */
    public function __construct(int $chunkSize, string $queryOrder,
                                array $filteredTables = [],
                                int $maxRecordsPerTable = null) {

        $this->chunkSize          = new QueryChunkSize($chunkSize);
        $this->queryOrder         = new QueryOrder($queryOrder);
        $this->filteredTables     = new QueryFilteredTables($filteredTables);
        $this->maxRecordsPerTable = new QueryMaxRecordsPerTable($this->chunkSize, $maxRecordsPerTable);

    }

    /**
     * @return int
     */
    public function getChunkSize(): int {
        return $this->chunkSize->getChunkSizeValue();
    }

    /**
     * @return string
     */
    public function getQueryOrder(): string {
        return $this->queryOrder->getQueryOrderValue();
    }

    /**
     * @return array
     */
    public function getFilteredTables(): array {
        return $this->filteredTables->getTables();
    }

    /**
     * @return int
     */
    public function getMaxRecordsPerTable(): int {
        return $this->maxRecordsPerTable->getMaxRecordsPerTableValue();
    }

}