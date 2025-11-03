<?php

namespace Core;

class Schema
{
    protected static $db;

    public static function setDatabase($db)
    {
        self::$db = $db;
    }

    protected static function db()
    {
        if (!self::$db) {
            self::$db = Database::getInstance();
        }
        return self::$db;
    }

    public static function create($table, $callback)
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        
        $sql = self::buildCreateTableSql($blueprint);
        self::db()->query($sql);
    }

    public static function table($table, $callback)
    {
        $blueprint = new Blueprint($table, true);
        $callback($blueprint);
        
        $statements = self::buildAlterTableSql($blueprint);
        foreach ($statements as $sql) {
            self::db()->query($sql);
        }
    }

    public static function drop($table)
    {
        $sql = "DROP TABLE IF EXISTS {$table}";
        self::db()->query($sql);
    }

    public static function dropIfExists($table)
    {
        self::drop($table);
    }

    public static function hasTable($table)
    {
        $sql = "SHOW TABLES LIKE ?";
        $result = self::db()->select($sql, [$table]);
        return !empty($result);
    }

    public static function hasColumn($table, $column)
    {
        $sql = "SHOW COLUMNS FROM {$table} LIKE ?";
        $result = self::db()->select($sql, [$column]);
        return !empty($result);
    }

    public static function rename($from, $to)
    {
        $sql = "RENAME TABLE {$from} TO {$to}";
        self::db()->query($sql);
    }

    protected static function buildCreateTableSql(Blueprint $blueprint)
    {
        $columns = [];
        
        foreach ($blueprint->getColumns() as $column) {
            $columns[] = self::buildColumnDefinition($column);
        }

        $sql = "CREATE TABLE {$blueprint->getTable()} (\n";
        $sql .= "  " . implode(",\n  ", $columns);

        // Add primary key
        $primaryKeys = $blueprint->getPrimaryKeys();
        if (!empty($primaryKeys)) {
            $sql .= ",\n  PRIMARY KEY (" . implode(', ', $primaryKeys) . ")";
        }

        // Add unique keys
        foreach ($blueprint->getUniqueKeys() as $key) {
            $sql .= ",\n  UNIQUE KEY ({$key})";
        }

        // Add indexes
        foreach ($blueprint->getIndexes() as $index) {
            $sql .= ",\n  INDEX ({$index})";
        }

        // Add foreign keys
        foreach ($blueprint->getForeignKeys() as $foreign) {
            $sql .= ",\n  FOREIGN KEY ({$foreign['column']}) REFERENCES {$foreign['table']}({$foreign['reference']})";
            if ($foreign['onDelete']) {
                $sql .= " ON DELETE {$foreign['onDelete']}";
            }
            if ($foreign['onUpdate']) {
                $sql .= " ON UPDATE {$foreign['onUpdate']}";
            }
        }

        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return $sql;
    }

    protected static function buildAlterTableSql(Blueprint $blueprint)
    {
        $statements = [];
        $table = $blueprint->getTable();

        foreach ($blueprint->getColumns() as $column) {
            $def = self::buildColumnDefinition($column);
            $statements[] = "ALTER TABLE {$table} ADD COLUMN {$def}";
        }

        foreach ($blueprint->getDropColumns() as $column) {
            $statements[] = "ALTER TABLE {$table} DROP COLUMN {$column}";
        }

        foreach ($blueprint->getIndexes() as $index) {
            $statements[] = "ALTER TABLE {$table} ADD INDEX ({$index})";
        }

        foreach ($blueprint->getForeignKeys() as $foreign) {
            $sql = "ALTER TABLE {$table} ADD FOREIGN KEY ({$foreign['column']}) ";
            $sql .= "REFERENCES {$foreign['table']}({$foreign['reference']})";
            if ($foreign['onDelete']) {
                $sql .= " ON DELETE {$foreign['onDelete']}";
            }
            if ($foreign['onUpdate']) {
                $sql .= " ON UPDATE {$foreign['onUpdate']}";
            }
            $statements[] = $sql;
        }

        return $statements;
    }

    protected static function buildColumnDefinition($column)
    {
        $sql = $column['name'] . ' ' . $column['type'];

        if (isset($column['length']) && $column['length']) {
            $sql .= "({$column['length']})";
        }

        if (isset($column['unsigned']) && $column['unsigned']) {
            $sql .= ' UNSIGNED';
        }

        if (isset($column['nullable']) && !$column['nullable']) {
            $sql .= ' NOT NULL';
        } else {
            $sql .= ' NULL';
        }

        if (isset($column['default']) && $column['default'] !== null) {
            $default = is_string($column['default']) ? "'{$column['default']}'" : $column['default'];
            $sql .= " DEFAULT {$default}";
        }

        if (isset($column['autoIncrement']) && $column['autoIncrement']) {
            $sql .= ' AUTO_INCREMENT';
        }

        return $sql;
    }
}
