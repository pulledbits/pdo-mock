<?php

namespace pulledbits\pdomock;

use PHPUnit\Framework\TestCase;

class PDOMockTest extends TestCase
{
    public function testcreateMockPDOCallback_When_StatementPrepareFalseReturnFromCallback_Expect_PDOStatementFailWithQuery() {
        $pdo = createMockPDOCallback();
        $pdo->callback(function (string $query, array $parameters) {
            return;
        });

        $statement = $pdo->prepare("SELECT * FROM table");

        $this->assertEquals("SELECT * FROM table", $statement->queryString);
    }

    public function testcreateMockPDOCallback_When_StatementPrepareWithPlaceholders_Expect_PDOStatementFetchAllWithQuery() {
        $pdo = createMockPDOCallback();
        $pdo->callback(function (string $query, array $parameters) {
            return createMockPDOStatement($query, [], $parameters, ['foo', 'bar']);
        });

        $statement = $pdo->prepare("SELECT * FROM table WHERE id = ? and app = ?");

        $this->assertEquals("SELECT * FROM table WHERE id = ? and app = ?", $statement->queryString);

        $statement->bindValue(1, 'foo');
        $statement->bindValue(2, 'bar');
    }

    public function testcreateMockPDOCallback_When_StatementPrepareWithNamedPlaceholders_Expect_PDOStatementFetchAllWithQuery() {
        $pdo = createMockPDOCallback();
        $pdo->callback(function (string $query, array $parameters) {
            return createMockPDOStatement($query, [], $parameters, ['foo', 'bar']);
        });

        $statement = $pdo->prepare("SELECT * FROM table WHERE id = :faa and app = :bor");

        $this->assertEquals("SELECT * FROM table WHERE id = :faa and app = :bor", $statement->queryString);

        $statement->bindValue(":faa", 'foo');
        $statement->bindValue(":bor", 'bar');
    }

    public function testcreateMockPDOCallback_When_StatementPrepare_Expect_PDOStatementFetchAllWithQuery() {
        $pdo = createMockPDOCallback();
        $pdo->callback(function (string $query, array $parameters) {
            return createMockPDOStatement($query, []);
        });

        $statement = $pdo->prepare("SELECT * FROM table");

        $this->assertEquals("SELECT * FROM table", $statement->queryString);
    }

    public function testcreateMockPDOCallback_When_StatementPrepare_Expect_PDOStatementRowCountWithQuery() {
        $pdo = createMockPDOCallback();
        $pdo->callback(function (string $query, array $parameters) {
            return createMockPDOStatement($query, 1);
        });

        $statement = $pdo->prepare("DELETE FROM table");

        $this->assertEquals("DELETE FROM table", $statement->queryString);
    }


    public function testcreateMockPDOCallback_When_StatementPrepareWithCALL_Expect_PDOStatementRowCountWithQuery() {
        $pdo = createMockPDOCallback();
        $pdo->callback(function (string $query, array $parameters) {
            return createMockPDOStatementProcedure($query);
        });

        $statement = $pdo->prepare("CALL procedure");
        $this->assertEquals("CALL procedure", $statement->queryString);
    }
}
