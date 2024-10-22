<?php

namespace Dankkomcg\MySQL\Sync\Database\Models;

class ConstraintForeignKey {

    /**
     * Foreign key identifier
     *
     * @var string
     */
    private string $constraintIdentifier;

    /**
     * @param string $constraintIdentifier
     */
    public function __construct(string $constraintIdentifier) {
        $this->constraintIdentifier = $constraintIdentifier;
    }

    /**
     * @return string
     */
    public function getConstraintIdentifier(): string
    {
        return $this->constraintIdentifier;
    }

}