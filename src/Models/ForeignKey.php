<?php

namespace Dankkomcg\MySQL\Sync\Models;

class ForeignKey {

    /**
     * @var Table
     */
    private Table $table;

    /**
     * @var Table
     */
    private Table $referencedTable;

    /**
     * @var Column
     */
    private Column $originColumn;

    /**
     * @var Column
     */
    private Column $referencedColumn;

    /**
     * Foreign key identifier
     *
     * @var string
     */
    private string $constraint;

    public function __construct(
        Table $table, Table $referencedTable, Column $originColumn, Column $referencedColumn, string $constraint
    ) {
        $this->table            = $table;
        $this->referencedTable  = $referencedTable;
        $this->originColumn     = $originColumn;
        $this->referencedColumn = $referencedColumn;
        $this->constraint       = $constraint;
    }

    /**
     * @return Table
     */
    public function getTable(): Table {
        return $this->table;
    }

    /**
     * @return Table
     */
    public function getReferencedTable(): Table
    {
        return $this->referencedTable;
    }

    public function getOriginColumn(): Column {
        return $this->originColumn;
    }

    /**
     * @return Column
     */
    public function getReferencedColumn(): Column
    {
        return $this->referencedColumn;
    }

    /**
     * @return string
     */
    public function getConstraint(): string
    {
        return $this->constraint;
    }

}