<?php

namespace pulledbits\pdomock;

function createMockPDOStatement(string $query, $results, array $expectedParameterIdentifiers = [], array $expectedParameterValues = []) {
    $arguments = [];

    if (is_array($results)) {
        $statementTrait = 'PDOStatementFetchAll';
        $arguments[] = $results;
    } elseif (is_int($results)) {
        $statementTrait = 'PDOStatementRowCount';
        $arguments[] = $results;
    } elseif ($results === false) {
        $statementTrait = 'PDOStatementFail';
    } elseif ($results === null) {
        $statementTrait = 'PDOStatementFetchAll';
        $arguments[] = [];
    } else {
        $statementTrait = 'PDOStatementFail';
    }
    $statement = instantiatePDOStatement($statementTrait, $query, $arguments);
    $statement->expectParameters(array_combine($expectedParameterIdentifiers, $expectedParameterValues));
    return $statement;
}
function createMockPDOStatementProcedure(string $query) {
    return instantiatePDOStatement('PDOStatementProcedure', $query, []);
}
function instantiatePDOStatement(string $statementTrait, string $query, $arguments) {
    $queryClassIdentifier = generateMockPDOStatement($statementTrait, $query);
    return new $queryClassIdentifier(...$arguments);
}
function generateMockPDOStatement(string $statementTrait, string $query) : string {
    $queryClassIdentifier = $statementTrait . '_' . uniqid();
    eval('class ' . $queryClassIdentifier . ' extends \PDOStatement { use ' . __NAMESPACE__ . '\\' . $statementTrait . '; public $queryString = \'' . $query . '\'; }');
    return $queryClassIdentifier;
}

trait PDOStatementFetchAll {
    use PDOStatement_ExpectParameters;

    private $results;

    public function __construct(array $results)
    {
        $this->results = $results;
    }

    public function fetchAll($how = \PDO::ATTR_DEFAULT_FETCH_MODE, $class_name = NULL, $ctor_args = NULL)
    {
        return $this->results;
    }

    public function fetch($fetch_style = null, $cursor_orientation = \PDO::FETCH_ORI_NEXT, $cursor_offset = 0)
    {
        return next($this->results);
    }

    public function execute($bound_input_params = NULL)
    {
        $this->checkParameters($bound_input_params);
        return true;
    }
}

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
        $this->checkParameters($bound_input_params);
        return false;
    }
};
trait PDOStatementRowCount {
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
        $this->checkParameters($bound_input_params);
        return true;
    }
};
trait PDOStatementProcedure {
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
        $this->checkParameters($bound_input_params);
        return true;
    }
};
trait PDOStatement_ExpectParameters {
    private $expectedParameters;
    private $givenParameters = [];
    public function expectParameters(array $expectedParameters) {
        $this->expectedParameters = $expectedParameters;
    }
    public function bindValue($parameter, $value, $data_type = \PDO::PARAM_STR)
    {
        if ($this->expectedParameters === null) {

        } elseif (array_key_exists($parameter, $this->expectedParameters)) {
            if ($this->expectedParameters[$parameter] === $value) {
                $this->givenParameters[$parameter] = $value;
                return;
            }
        }
        trigger_error('Unexpected parameter ' . var_export($parameter, true) . ' with value ' . var_export($value, true) . '. ' . count($this->expectedParameters) . ' parameter(s) expected');
    }

    private function checkParameters($bound_input_params = []) {
        if (is_array($bound_input_params)) {
            foreach ($bound_input_params as $bound_input_param_id => $bound_input_param) {
                if (is_int($bound_input_param)) {
                    $this->bindValue($bound_input_param_id + 1, $bound_input_param);
                } else {
                    $this->bindValue($bound_input_param_id, $bound_input_param);
                }
            }
        }

        if (count(array_diff_assoc($this->expectedParameters, $this->givenParameters)) > 0) {
            throw new \PDOException('SQLSTATE[HY093]: Invalid parameter number:');
        }

    }
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
            $statement = createMockPDOStatement($query, false);
            foreach ($this->callback as $callback) {
                preg_match_all('/(?<parameter>:\w+|\?)/', $query, $matches);
                $expectedParameters = [];
                $parameterCount = 0;
                foreach ($matches['parameter'] as $parameterIdentifier) {
                    if ($parameterIdentifier === '?') {
                        $expectedParameters[] = ++$parameterCount;
                    } else {
                        $expectedParameters[] = $parameterIdentifier;
                    }
                }
                $callbackStatement = call_user_func($callback, $query, $expectedParameters);
                if ($callbackStatement !== null) {
                    $statement = $callbackStatement;
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