<?php

namespace Dankkomcg\MySQL\Sync\Database\Tables\Conditions\DTOs;

use Dankkomcg\MySQL\Sync\Exceptions\QueryOrderException;

class QueryOrder {

    private string $queryOrder;

    /**
     * @param string $queryOrder
     * @throws QueryOrderException
     */
    public function __construct(string $queryOrder  ) {
        $this->setQueryOrder($queryOrder);
    }

    /**
     * @throws QueryOrderException
     */
    private function setQueryOrder(string $queryOrder): void {

        if(!in_array($queryOrder, ['ASC', 'DESC'])) {
            throw new QueryOrderException(
                sprintf(
                    "%s is a not valid value to define the synchronization order.", $queryOrder
                )
            );
        }

        $this->queryOrder = $queryOrder;

    }

    public function getQueryOrderValue(): string {
        return $this->queryOrder;
    }

}