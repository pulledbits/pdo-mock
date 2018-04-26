<?php

namespace pulledbits\pdomock;

function createMockPDOStatement($results, array $expectedParameterIdentifiers = [], array $expectedParameterValues = []) {
    if (is_array($results)) {
        $statement = createMockPDOStatementFetchAll($results);
    } elseif (is_int($results)) {
        $statement = createMockPDOStatementRowCount($results);
    } elseif ($results === false) {
        $statement = createMockPDOStatementFail($results);
    } elseif ($results === null) {
        $statement = createMockPDOStatementFetchAll([]);
    } else {
        $statement = createMockPDOStatementFail(false);
    }
    $statement->expectParameters(array_combine($expectedParameterIdentifiers, $expectedParameterValues));
    return $statement;
}

trait PDOStatement_ExpectParameters {


    private $expectedParameters;
    public function expectParameters(array $expectedParameters) {
        $this->expectedParameters = $expectedParameters;
    }
    public function bindValue($parameter, $value, $data_type = PDO::PARAM_STR)
    {
        if ($this->expectedParameters === null) {

        } elseif (array_key_exists($parameter, $this->expectedParameters) === false) {
            trigger_error('Unexpected parameter ' . $parameter . ' with value ' . $value);
        }
    }
}

function createMockPDOStatementFetchAll(array $results) {
    return new class($results) extends \PDOStatement
    {
        use PDOStatement_ExpectParameters;

        private $results;

        public function __construct(array $results)
        {
            $this->results = $results;
        }


        public function fetchAll($how = \PDO::ATTR_DEFAULT_FETCH_MODE, $class_name = NULL, $ctor_args = NULL)
        {
            if ($how === \PDO::ATTR_DEFAULT_FETCH_MODE) {
                $how = \PDO::FETCH_ASSOC;
            }

            if ($how === \PDO::FETCH_ASSOC) {
                return $this->results;
            }
        }

        public function fetch($fetch_style = null, $cursor_orientation = PDO::FETCH_ORI_NEXT, $cursor_offset = 0)
        {
            if ($fetch_style === \PDO::FETCH_ASSOC) {
                return next($this->results);
            }
        }

        public function execute($bound_input_params = NULL)
        {
            return true;
        }
    };
}

function createMockPDOStatementRowCount(int $results) {
    return new class($results) extends \PDOStatement
    {
        use PDOStatement_ExpectParameters;

        private $results;

        public function __construct(int $results)
        {
            $this->results = $results;
        }

        public function rowCount()
        {
            return $this->results;
        }

        public function execute($bound_input_params = NULL)
        {
            return true;
        }
    };
}
function createMockPDOStatementProcedure() {
    return new class extends \PDOStatement
    {
        use PDOStatement_ExpectParameters;

        public function __construct()
        {
        }

        public function rowCount()
        {
            return 0;
        }

        public function fetchAll($how = NULL, $class_name = NULL, $ctor_args = NULL)
        {
            return [];
        }

        public function execute($bound_input_params = NULL)
        {
            return true;
        }
    };
}

function createMockPDOStatementFail(string $query) {

    trait PDOStatementFail {
        use PDOStatement_ExpectParameters;

        public function rowCount()
        {
            return 0;
        }

        public function fetchAll($how = NULL, $class_name = NULL, $ctor_args = NULL)
        {
            return false;
        }

        public function execute($bound_input_params = NULL)
        {
            return false;
        }
    };

    $queryClassIdentifier = 'PDOStatement_' . uniqid();
    eval('class ' . $queryClassIdentifier . ' extends \PDOStatement { use ' . __NAMESPACE__ . '\\PDOStatementFail; public $queryString = \'' . $query . '\'; }');

    return new $queryClassIdentifier;

}

function createMockPDOCallback() {

    return new class() extends \PDO
    {

        private $callback = [];

        public function __construct()
        {
        }

        public function callback(callable $callback)
        {
            $this->callback[] = $callback;
        }

        public function prepare($query, $options = null)
        {
            $statement = createMockPDOStatementFail($query);
            foreach ($this->callback as $callback) {
                preg_match_all('/(?<parameter>:\w+)/', $query, $matches);
                $statement = call_user_func($callback, $query, $matches['parameter']);
                if ($statement !== null) {
                    break;
                }
            }
            return $statement;
        }
    };
}

function createTableResult(string $schemaIdentifier, string $tableIdentifier) {
    return ['Table_in_' . $schemaIdentifier => $tableIdentifier, 'Table_type' => 'BASE_TABLE'];
}
function createViewResult(string $schemaIdentifier, string $tableIdentifier) {
    return ['Table_in_' . $schemaIdentifier => $tableIdentifier, 'Table_type' => 'VIEW'];
}
function createColumnResult(string $columnIdentifier, string $typeIdentifier, bool $nullable, bool $autoincrement = false) {
    return [
        'Field' => $columnIdentifier,
        'Type' => $typeIdentifier,
        'Null' => $nullable ? 'YES' : 'NO',
        'Key' => 'PRI',
        'Default' => '',
        'Extra' => $autoincrement ? 'auto_increment' : '',
        'Comment' => '',
        'CharacterSet' => '',
        'Collation' => ''
    ];
}
function createConstraintResult(string $identifier, string $localColumnIdentifier, string $referencedTableIdentifier, string $referencedColumnIdentifier) {
    return [
        'CONSTRAINT_NAME' => $identifier,
        'COLUMN_NAME' => $localColumnIdentifier,
        'REFERENCED_TABLE_NAME' => $referencedTableIdentifier,
        'REFERENCED_COLUMN_NAME' => $referencedColumnIdentifier
    ];
}

define('CONSTRAINT_KEY_UNIQUE', 'UNIQUE');
define('CONSTRAINT_KEY_FOREIGN', 'FOREIGN');
define('CONSTRAINT_KEY_PRIMARY', 'PRIMARY');
function createIndexResult(string $tableIdentifier, string $keyIdentifier, string $columnIdentifier) {
    return [
        'Table' => $tableIdentifier,
        'Non_unique' => '0',
        'Key_name' => $keyIdentifier,
        'Seq_in_index' => '1',
        'Column_name' => $columnIdentifier,
        'Collation' => 'A',
        'Cardinality' => '1',
        'Sub_part' => null,
        'Packed' => null,
        'Null' => '',
        'Index_type' => 'BTREE',
        'Comment' => '',
        'Index_comment' => ''
    ];
}