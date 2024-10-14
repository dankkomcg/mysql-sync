<?php

namespace Dankkomcg\MySQL\Sync\Database\Models;

class Column {

    private string $name;

    public function __construct(string $name) {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

}