<?php

namespace Core;

class Blueprint
{
    protected $table;
    protected $columns = [];
    protected $primaryKeys = [];
    protected $uniqueKeys = [];
    protected $indexes = [];
    protected $foreignKeys = [];
    protected $dropColumns = [];
    protected $isAltering = false;
    protected $currentForeign;

    public function __construct($table, $isAltering = false)
    {
        $this->table = $table;
        $this->isAltering = $isAltering;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getPrimaryKeys()
    {
        return $this->primaryKeys;
    }

    public function getUniqueKeys()
    {
        return $this->uniqueKeys;
    }

    public function getIndexes()
    {
        return $this->indexes;
    }

    public function getForeignKeys()
    {
        return $this->foreignKeys;
    }

    public function getDropColumns()
    {
        return $this->dropColumns;
    }

    protected function addColumn($name, $type, $length = null)
    {
        $column = [
            'name' => $name,
            'type' => $type,
            'length' => $length,
            'nullable' => false,
            'default' => null,
            'unsigned' => false,
            'autoIncrement' => false
        ];

        $this->columns[] = $column;
        return $this;
    }

    public function id($name = 'id')
    {
        $this->bigIncrements($name);
        return $this;
    }

    public function bigIncrements($name)
    {
        $this->addColumn($name, 'BIGINT', null);
        end($this->columns);
        $key = key($this->columns);
        $this->columns[$key]['unsigned'] = true;
        $this->columns[$key]['autoIncrement'] = true;
        $this->primaryKeys[] = $name;
        return $this;
    }

    public function increments($name)
    {
        $this->addColumn($name, 'INT', null);
        end($this->columns);
        $key = key($this->columns);
        $this->columns[$key]['unsigned'] = true;
        $this->columns[$key]['autoIncrement'] = true;
        $this->primaryKeys[] = $name;
        return $this;
    }

    public function string($name, $length = 255)
    {
        return $this->addColumn($name, 'VARCHAR', $length);
    }

    public function text($name)
    {
        return $this->addColumn($name, 'TEXT', null);
    }

    public function mediumText($name)
    {
        return $this->addColumn($name, 'MEDIUMTEXT', null);
    }

    public function longText($name)
    {
        return $this->addColumn($name, 'LONGTEXT', null);
    }

    public function integer($name)
    {
        return $this->addColumn($name, 'INT', null);
    }

    public function bigInteger($name)
    {
        return $this->addColumn($name, 'BIGINT', null);
    }

    public function tinyInteger($name)
    {
        return $this->addColumn($name, 'TINYINT', null);
    }

    public function smallInteger($name)
    {
        return $this->addColumn($name, 'SMALLINT', null);
    }

    public function float($name, $precision = 8, $scale = 2)
    {
        return $this->addColumn($name, 'FLOAT', "{$precision},{$scale}");
    }

    public function double($name, $precision = 8, $scale = 2)
    {
        return $this->addColumn($name, 'DOUBLE', "{$precision},{$scale}");
    }

    public function decimal($name, $precision = 8, $scale = 2)
    {
        return $this->addColumn($name, 'DECIMAL', "{$precision},{$scale}");
    }

    public function boolean($name)
    {
        return $this->addColumn($name, 'TINYINT', 1);
    }

    public function date($name)
    {
        return $this->addColumn($name, 'DATE', null);
    }

    public function dateTime($name)
    {
        return $this->addColumn($name, 'DATETIME', null);
    }

    public function timestamp($name)
    {
        return $this->addColumn($name, 'TIMESTAMP', null);
    }

    public function time($name)
    {
        return $this->addColumn($name, 'TIME', null);
    }

    public function json($name)
    {
        return $this->addColumn($name, 'JSON', null);
    }

    public function enum($name, array $values)
    {
        $valueString = implode("','", $values);
        return $this->addColumn($name, "ENUM('{$valueString}')", null);
    }

    public function timestamps()
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
        return $this;
    }

    public function softDeletes()
    {
        $this->timestamp('deleted_at')->nullable();
        return $this;
    }

    public function nullable()
    {
        end($this->columns);
        $key = key($this->columns);
        $this->columns[$key]['nullable'] = true;
        return $this;
    }

    public function default($value)
    {
        end($this->columns);
        $key = key($this->columns);
        $this->columns[$key]['default'] = $value;
        return $this;
    }

    public function unsigned()
    {
        end($this->columns);
        $key = key($this->columns);
        $this->columns[$key]['unsigned'] = true;
        return $this;
    }

    public function unique()
    {
        end($this->columns);
        $key = key($this->columns);
        $this->uniqueKeys[] = $this->columns[$key]['name'];
        return $this;
    }

    public function index($columns = null)
    {
        if ($columns === null) {
            end($this->columns);
            $key = key($this->columns);
            $columns = $this->columns[$key]['name'];
        }

        $this->indexes[] = is_array($columns) ? implode(',', $columns) : $columns;
        return $this;
    }

    public function foreign($column)
    {
        $this->currentForeign = [
            'column' => $column,
            'table' => null,
            'reference' => 'id',
            'onDelete' => null,
            'onUpdate' => null
        ];
        return $this;
    }

    public function references($column)
    {
        $this->currentForeign['reference'] = $column;
        return $this;
    }

    public function on($table)
    {
        $this->currentForeign['table'] = $table;
        $this->foreignKeys[] = $this->currentForeign;
        return $this;
    }

    public function onDelete($action)
    {
        end($this->foreignKeys);
        $key = key($this->foreignKeys);
        $this->foreignKeys[$key]['onDelete'] = strtoupper($action);
        return $this;
    }

    public function onUpdate($action)
    {
        end($this->foreignKeys);
        $key = key($this->foreignKeys);
        $this->foreignKeys[$key]['onUpdate'] = strtoupper($action);
        return $this;
    }

    public function dropColumn($column)
    {
        $this->dropColumns[] = $column;
        return $this;
    }

    public function primary($columns)
    {
        $this->primaryKeys = is_array($columns) ? $columns : [$columns];
        return $this;
    }
}
