<?php
declare(strict_types=1);

namespace Pageflow\Infrastructure\Database\Query;

class InsertBuilder extends InsertUpdateBuilder
{
    /** @var string */
    protected $columns = '';

    /** @var string */
    protected $values = '';

    /**
     * adds column to insert query
     * @param string $name
     * @param $value
    */
    public function addColumn(string $name, $value)
    {
        // SECURITY REVIEW: Ensure `$name` is a trusted identifier (column name).
        // It is concatenated directly into SQL and cannot be parameterized by pg_query_params.
        // Consider validating against an allowlist of known column names to avoid SQL injection via identifiers.
        $this->args[] = $value;
        if (mb_strlen($this->columns) > 0) {
            $this->columns .= ", ";
        }
        $this->columns .= $name;
        if (mb_strlen($this->values) > 0) {
            $this->values .= ", ";
        }
        $argNum = count($this->args);
        $this->values .= "$".$argNum;
    }

    /**
     * sets insert query
     */
    public function setSql()
    {
        // SECURITY REVIEW: Ensure `$this->dbTable` is a trusted identifier (table name).
        // Consider validating against an allowlist or mapping of table names.
        $this->sql = "INSERT INTO $this->dbTable ($this->columns) VALUES($this->values)";
    }
}
