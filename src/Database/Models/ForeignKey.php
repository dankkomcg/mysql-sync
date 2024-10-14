<?php

namespace Dankkomcg\MySQL\Sync\Database\Models;

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
     * @var ConstraintForeignKey
     */
    private ConstraintForeignKey $constraint;

    public function __construct(
        Table $table, Table $referencedTable, Column $originColumn, Column $referencedColumn, ConstraintForeignKey $constraint
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
     * @return ConstraintForeignKey
     */
    public function getConstraint(): ConstraintForeignKey
    {
        return $this->constraint;
    }

}