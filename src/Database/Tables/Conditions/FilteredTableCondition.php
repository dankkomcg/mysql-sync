<?php

namespace Dankkomcg\MySQL\Sync\Database\Tables\Conditions;

use Dankkomcg\MySQL\Sync\Database\Models\TemplateSchema;
use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\DTOs\QueryChunkSize;
use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\DTOs\QueryOrder;
use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\Resolvers\DynamicDependencyResolver;
use Dankkomcg\MySQL\Sync\Exceptions\QueryOrderException;
use Dankkomcg\MySQL\Sync\Exceptions\TableSyncException;

final class FilteredTableCondition extends TableCondition {

    private array $filteredTables;

    /**
     * @throws TableSyncException
     */
    public function __construct(QueryChunkSize $chunkSize, QueryOrder $queryOrder, array $filteredTables = []) {
        parent::__construct($chunkSize, $queryOrder);
        $this->setFilteredTables($filteredTables);
    }



    /**
     * @throws QueryOrderException
     */
    public function getTablesBasedOnCriteria(TemplateSchema $sourceSchema): array {

        $dynamicDependencyResolver = new DynamicDependencyResolver($sourceSchema);

        return $dynamicDependencyResolver->getFilteredTablesInDependencyOrder($this->filteredTables);

    }

}