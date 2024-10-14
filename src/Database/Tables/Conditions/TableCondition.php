<?php

namespace Dankkomcg\MySQL\Sync\Database\Tables\Conditions;

use Dankkomcg\MySQL\Sync\Database\Models\TemplateSchema;
use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\DTOs\QueryChunkSize;
use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\DTOs\QueryMaxRecordsPerTable;
use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\DTOs\QueryOrder;
use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\Resolvers\DependencyResolver;
use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\Resolvers\DynamicDependencyResolver;
use Dankkomcg\MySQL\Sync\Exceptions\QueryOrderException;

class TableCondition {

    /**
     * @var QueryChunkSize
     */
    protected QueryChunkSize $chunkSize;

    /**
     * @var QueryOrder
     */
    private QueryOrder $queryOrder;

    public function __construct(QueryChunkSize $chunkSize, QueryOrder $queryOrder) {
        $this->chunkSize          = $chunkSize;
        $this->queryOrder         = $queryOrder;
    }

    /**
     * Build the criteria to get the data result
     *
     * @param TemplateSchema $sourceSchema
     * @return array
     * @throws QueryOrderException
     */
    public function getTablesBasedOnCriteria(TemplateSchema $sourceSchema): array {

        $dynamicDependencyResolver = new DynamicDependencyResolver($sourceSchema);

        return $dynamicDependencyResolver->getFilteredTablesInDependencyOrder([
            'dashboards'
        ]);

    }

}