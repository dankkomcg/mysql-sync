<?php

namespace Dankkomcg\MySQL\Sync\Database\Tables\Conditions\DTOs;

use Dankkomcg\MySQL\Sync\Exceptions\ChunkSizeValueException;

class QueryChunkSize {

    private int $chunkSize;

    /**
     * @throws ChunkSizeValueException
     */
    public function __construct(int $chunkSize) {
        $this->setChunkSize($chunkSize);
    }

    /**
     * @throws ChunkSizeValueException
     */
    private function setChunkSize(int $chunkSize): void {

        if($chunkSize <= 0) {
            throw new ChunkSizeValueException(
                sprintf(
                    "%s as chunk size can't be less or equal to zero", $chunkSize
                )
            );
        }

        $this->chunkSize = $chunkSize;
    }

    /**
     * @return int
     */
    public function getChunkSizeValue(): int
    {
        return $this->chunkSize;
    }

}