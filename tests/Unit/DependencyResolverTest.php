<?php

namespace Dankkomcg\MySQL\Sync\Tests\Unit;

use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\Resolvers\DependencyResolver;
use PDO;
use PHPUnit\Framework\TestCase;

class DependencyResolverTest extends TestCase
{
    private PDO $pdo;
    private DependencyResolver $resolver;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->resolver = new DependencyResolver();
    }

    public function testGetTablesInDependencyOrder() {
        $schema = 'test_schema';
        $tables = ['table1', 'table2', 'table3'];
        $foreignKeys = [
            ['TABLE_NAME' => 'table2', 'REFERENCED_TABLE_NAME' => 'table1'],
            ['TABLE_NAME' => 'table3', 'REFERENCED_TABLE_NAME' => 'table2'],
        ];

        $stmtTables = $this->createMock(\PDOStatement::class);
        $stmtTables->method('fetchAll')->willReturn($tables);

        $stmtForeignKeys = $this->createMock(\PDOStatement::class);
        $stmtForeignKeys->method('fetchAll')->willReturn($foreignKeys);

        $this->pdo->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtForeignKeys, $stmtTables);

        $result = $this->resolver->getTablesInDependencyOrder($this->pdo);
        $this->assertEquals(
            [
                'table1', 'table2', 'table3'
            ], $result
        );

    }
}