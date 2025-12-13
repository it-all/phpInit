<?php
declare(strict_types=1);

namespace Pageflow\Infrastructure\Database\Query;

class UpdateBuilder extends InsertUpdateBuilder {
    public $updateOnColumnName;
    public $updateOnColumnValue;
    public $setColumnsValues;

    function __construct(string $dbTable, string $updateOnColumnName, $updateOnColumnValue)
    {
        $this->updateOnColumnName = $updateOnColumnName;
        $this->updateOnColumnValue = $updateOnColumnValue;
        parent::__construct($dbTable);
    }

    /**
     * adds column to update query
     * @param string $name
     * @param $value
     */
    public function addColumn(string $name, $value)
    {
        // SECURITY REVIEW: Validate `$name` against expected column names.
        // It is concatenated directly, which may allow SQL injection through identifiers if not trusted.
        $this->args[] = $value;
        if (count($this->args) > 1) {
            $this->setColumnsValues .= ", ";
        }
        $argNum = count($this->args);
        $this->setColumnsValues .= "$name = \$".$argNum;
    }

    /**
     * @param array $updateColumns
     */
    public function addColumnsArray(array $updateColumns)
    {
        foreach ($updateColumns as $name => $value) {
            $this->addColumn($name, $value);
        }
    }

    /**
     * sets update query
     */
    public function setSql()
    {
        // SECURITY REVIEW: Validate `$this->dbTable` and `$this->updateOnColumnName` (identifiers).
        $this->args[] = $this->updateOnColumnValue;
        $lastArgNum = count($this->args);
        $this->sql = "UPDATE $this->dbTable SET $this->setColumnsValues WHERE $this->updateOnColumnName = $".$lastArgNum;
    }
}
