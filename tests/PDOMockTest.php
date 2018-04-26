<?php

namespace pulledbits\pdomock;

use PHPUnit\Framework\TestCase;

class PDOMockTest extends TestCase
{

    public function testcreateMockPDOCallback_When_StatementPrepare_Expect_PDOStatementFetchAllWithQuery() {
        $pdo = createMockPDOCallback(function (string $query, array $parameters) {
            return false;
        });

        $statement = $pdo->prepare("SELECT * FROM table");

        $this->assertEquals("SELECT * FROM table", $statement->queryString);
    }


}
