<?php

namespace Core;

class QueryBuilder
{
    protected $db;
    protected $table;
    protected $modelClass;
    protected $wheres = [];
    protected $orderBys = [];
    protected $limitValue;
    protected $offsetValue;
    protected $selectColumns = ['*'];
    protected $joins = [];
    protected $bindings = [];

    public function __construct($db, $table, $modelClass = null)
    {
        $this->db = $db;
        $this->table = $table;
        $this->modelClass = $modelClass;
    }

    public function select(...$columns)
    {
        $this->selectColumns = empty($columns) ? ['*'] : $columns;
        return $this;
    }

    public function where($column, $operator = null, $value = null)
    {
        // If only two arguments, assume operator is '='
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function orWhere($column, $operator = null, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function whereIn($column, array $values)
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND'
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function whereNull($column)
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'AND'
        ];

        return $this;
    }

    public function whereNotNull($column)
    {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => 'AND'
        ];

        return $this;
    }

    public function orderBy($column, $direction = 'ASC')
    {
        $this->orderBys[] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];

        return $this;
    }

    public function limit($limit)
    {
        $this->limitValue = $limit;
        return $this;
    }

    public function offset($offset)
    {
        $this->offsetValue = $offset;
        return $this;
    }

    public function join($table, $first, $operator, $second)
    {
        $this->joins[] = [
            'type' => 'inner',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    public function leftJoin($table, $first, $operator, $second)
    {
        $this->joins[] = [
            'type' => 'left',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    public function get()
    {
        $sql = $this->buildSelectSql();
        $results = $this->db->select($sql, $this->bindings);

        if ($this->modelClass) {
            return array_map(function($row) {
                $model = new $this->modelClass();
                foreach ($row as $key => $value) {
                    $model->setAttribute($key, $value);
                }
                $model->exists = true;
                return $model;
            }, $results);
        }

        return $results;
    }

    public function first()
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    public function count()
    {
        $this->selectColumns = ['COUNT(*) as count'];
        $sql = $this->buildSelectSql();
        $result = $this->db->selectOne($sql, $this->bindings);
        return (int) $result['count'];
    }

    public function exists()
    {
        return $this->count() > 0;
    }

    public function paginate($perPage = 15, $page = 1)
    {
        $total = $this->count();
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $this->limit($perPage)->offset($offset);
        $data = $this->get();

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $totalPages,
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }

    protected function buildSelectSql()
    {
        $sql = "SELECT " . implode(', ', $this->selectColumns);
        $sql .= " FROM {$this->table}";

        // Add joins
        foreach ($this->joins as $join) {
            $type = strtoupper($join['type']);
            $sql .= " {$type} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        // Add where clauses
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWhereClauses();
        }

        // Add order by
        if (!empty($this->orderBys)) {
            $sql .= " ORDER BY ";
            $orderParts = [];
            foreach ($this->orderBys as $order) {
                $orderParts[] = "{$order['column']} {$order['direction']}";
            }
            $sql .= implode(', ', $orderParts);
        }

        // Add limit and offset
        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        if ($this->offsetValue !== null) {
            $sql .= " OFFSET {$this->offsetValue}";
        }

        return $sql;
    }

    protected function buildWhereClauses()
    {
        $clauses = [];

        foreach ($this->wheres as $index => $where) {
            $boolean = $index === 0 ? '' : " {$where['boolean']} ";

            switch ($where['type']) {
                case 'basic':
                    $clauses[] = $boolean . "{$where['column']} {$where['operator']} ?";
                    break;

                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $clauses[] = $boolean . "{$where['column']} IN ({$placeholders})";
                    break;

                case 'null':
                    $clauses[] = $boolean . "{$where['column']} IS NULL";
                    break;

                case 'not_null':
                    $clauses[] = $boolean . "{$where['column']} IS NOT NULL";
                    break;
            }
        }

        return implode('', $clauses);
    }

    public function insert(array $data)
    {
        return $this->db->insert($this->table, $data);
    }

    public function update(array $data)
    {
        if (empty($this->wheres)) {
            throw new \Exception("Cannot update without where clause");
        }

        $setParts = [];
        $values = [];

        foreach ($data as $column => $value) {
            $setParts[] = "{$column} = ?";
            $values[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts);
        
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWhereClauses();
            $values = array_merge($values, $this->bindings);
        }

        $stmt = $this->db->query($sql, $values);
        return $stmt->rowCount();
    }

    public function delete()
    {
        if (empty($this->wheres)) {
            throw new \Exception("Cannot delete without where clause");
        }

        $sql = "DELETE FROM {$this->table}";
        
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWhereClauses();
        }

        $stmt = $this->db->query($sql, $this->bindings);
        return $stmt->rowCount();
    }

    public function raw($sql, $bindings = [])
    {
        return $this->db->select($sql, $bindings);
    }

    public function __call($method, $parameters)
    {
        throw new \Exception("Method {$method} does not exist on QueryBuilder");
    }
}
