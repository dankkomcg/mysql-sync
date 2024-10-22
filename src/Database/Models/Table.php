<?php

namespace Dankkomcg\MySQL\Sync\Database\Models;

class Table {

    private string $name;

    private array $foreignKeys;

    public function __construct(string $name) {
        $this->name = $name;
        $this->foreignKeys = [];
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param ForeignKey $foreignKey
     * @return void
     */
    public function addForeignKey(ForeignKey $foreignKey): void {
        $this->foreignKeys[] = $foreignKey;
    }

    /**
     * @return array
     */
    public function getForeignKeys(): array {
        return $this->foreignKeys;
    }

}