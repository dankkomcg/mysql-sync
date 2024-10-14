<?php

namespace Dankkomcg\MySQL\Sync\Tests\Unit;

use Dankkomcg\MySQL\Sync\Database\Services\TableSync;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class TableSyncTest extends TestCase
{
    private $sourcePdo;
    private $targetPdo;
    private $tableSync;

    protected function setUp(): void
    {
        $this->sourcePdo = $this->createMock(PDO::class);
        $this->targetPdo = $this->createMock(PDO::class);
        $this->tableSync = new TableSync(
            $this->sourcePdo,
            $this->targetPdo,
            1000, // chunkSize
            null, // maxRecordsPerTable
            'DESC' // syncDirection
        );
    }

    public function testSyncTables()
    {
        $tables = ['table1', 'table2'];
        $sourceSchema = 'source_schema';
        $targetSchema = 'target_schema';

        // Mock getColumnsInfo
        $columnsInfo = [['Field' => 'id', 'Type' => 'int', 'Key' => 'PRI']];
        $stmtColumns = $this->createMock(PDOStatement::class);
        $stmtColumns->method('fetchAll')->willReturn($columnsInfo);
        $this->sourcePdo->method('query')->willReturn($stmtColumns);

        // Mock getForeignKeys
        $foreignKeys = [];
        $stmtForeignKeys = $this->createMock(PDOStatement::class);
        $stmtForeignKeys->method('fetchAll')->willReturn($foreignKeys);
        $this->sourcePdo->method('prepare')->willReturn($stmtForeignKeys);

        // Mock getTotalRows
        $stmtTotalRows = $this->createMock(PDOStatement::class);
        $stmtTotalRows->method('fetchColumn')->willReturn(10);
        $this->sourcePdo->method('query')->willReturn($stmtTotalRows);

        // Mock fetchRows
        $rows = [['id' => 1], ['id' => 2]];
        $stmtFetchRows = $this->createMock(PDOStatement::class);
        $stmtFetchRows->method('fetchAll')->willReturn($rows);
        $this->sourcePdo->method('query')->willReturn($stmtFetchRows);

        // Mock insertRows
        $stmtInsert = $this->createMock(PDOStatement::class);
        $stmtInsert->expects($this->atLeastOnce())->method('execute');
        $this->targetPdo->method('prepare')->willReturn($stmtInsert);

        // Mock transaction methods
        $this->targetPdo->expects($this->once())->method('beginTransaction');
        $this->targetPdo->expects($this->once())->method('commit');

        // Execute syncTables
        $this->tableSync->syncSchemaTables($tables, $sourceSchema, $targetSchema);

        // Assert that the process completed without exceptions
        $this->assertTrue(true);
    }

    public function testSyncTablesWithError()
    {
        $tables = ['table1'];
        $sourceSchema = 'source_schema';
        $targetSchema = 'target_schema';

        // Mock methods to throw an exception
        $this->sourcePdo->method('query')->willThrowException(
            new \Exception('Test exception')
        );

        // Mock transaction methods
        $this->targetPdo->expects($this->once())->method('beginTransaction');
        $this->targetPdo->expects($this->once())->method('rollBack');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        // Execute syncTables
        $this->tableSync->syncSchemaTables($tables, $sourceSchema, $targetSchema);
    }
}