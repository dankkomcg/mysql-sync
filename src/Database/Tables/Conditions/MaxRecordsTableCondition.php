<?php

namespace Dankkomcg\MySQL\Sync\Database\Tables\Conditions;

use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\DTOs\QueryChunkSize;
use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\DTOs\QueryMaxRecordsPerTable;
use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\DTOs\QueryOrder;

final class MaxRecordsTableCondition extends TableCondition {

    protected QueryMaxRecordsPerTable $maxRecordsPerTable;

    /**
     * @param QueryChunkSize $chunkSize
     * @param QueryOrder $queryOrder
     * @param QueryMaxRecordsPerTable $maxRecords
     */
    public function __construct(QueryChunkSize $chunkSize, QueryOrder $queryOrder, QueryMaxRecordsPerTable $maxRecords) {
        parent::__construct($chunkSize, $queryOrder);
        $this->maxRecordsPerTable = $maxRecords;
    }

}