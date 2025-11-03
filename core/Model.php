<?php

namespace Core;

abstract class Model
{
    protected static $table;
    protected static $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = [];
    protected $casts = [];
    protected $attributes = [];
    protected $original = [];
    protected $exists = false;
    protected $timestamps = true;

    protected static $db;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

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

    protected static function query()
    {
        $table = static::$table ?? static::getTableName();
        return new QueryBuilder(self::db(), $table, static::class);
    }

    protected static function getTableName()
    {
        if (isset(static::$table)) {
            return static::$table;
        }
        
        $class = (new \ReflectionClass(static::class))->getShortName();
        return strtolower($class) . 's';
    }

    // Query methods (similar to Eloquent)
    public static function all()
    {
        return static::query()->get();
    }

    public static function find($id)
    {
        return static::query()->where(static::$primaryKey, $id)->first();
    }

    public static function findOrFail($id)
    {
        $result = static::find($id);
        if (!$result) {
            throw new \Exception("Model not found with ID: {$id}", 404);
        }
        return $result;
    }

    public static function where($column, $operator = null, $value = null)
    {
        return static::query()->where($column, $operator, $value);
    }

    public static function whereIn($column, array $values)
    {
        return static::query()->whereIn($column, $values);
    }

    public static function orderBy($column, $direction = 'ASC')
    {
        return static::query()->orderBy($column, $direction);
    }

    public static function limit($limit)
    {
        return static::query()->limit($limit);
    }

    public static function create(array $attributes)
    {
        $instance = new static($attributes);
        $instance->save();
        return $instance;
    }

    public function save()
    {
        if ($this->timestamps) {
            $timestamp = date('Y-m-d H:i:s');
            if (!$this->exists) {
                $this->attributes['created_at'] = $timestamp;
            }
            $this->attributes['updated_at'] = $timestamp;
        }

        if ($this->exists) {
            return $this->performUpdate();
        } else {
            return $this->performInsert();
        }
    }

    protected function performInsert()
    {
        $attributes = $this->getAttributesForInsert();
        $table = static::getTableName();
        
        $id = self::db()->insert($table, $attributes);
        
        if ($id) {
            $this->attributes[static::$primaryKey] = $id;
            $this->exists = true;
            $this->syncOriginal();
            return true;
        }
        
        return false;
    }

    protected function performUpdate()
    {
        $attributes = $this->getDirtyAttributes();
        
        if (empty($attributes)) {
            return true;
        }

        $table = static::getTableName();
        $id = $this->attributes[static::$primaryKey];
        
        $result = self::db()->update($table, $attributes, [
            static::$primaryKey => $id
        ]);

        if ($result) {
            $this->syncOriginal();
        }

        return $result;
    }

    public function delete()
    {
        if (!$this->exists) {
            return false;
        }

        $table = static::getTableName();
        $id = $this->attributes[static::$primaryKey];
        
        $result = self::db()->delete($table, [
            static::$primaryKey => $id
        ]);

        if ($result) {
            $this->exists = false;
        }

        return $result;
    }

    public static function destroy($ids)
    {
        $ids = is_array($ids) ? $ids : func_get_args();
        $count = 0;

        foreach ($ids as $id) {
            $model = static::find($id);
            if ($model && $model->delete()) {
                $count++;
            }
        }

        return $count;
    }

    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    public function update(array $attributes)
    {
        $this->fill($attributes);
        return $this->save();
    }

    protected function isFillable($key)
    {
        return empty($this->fillable) || in_array($key, $this->fillable);
    }

    protected function getAttributesForInsert()
    {
        $attributes = [];
        foreach ($this->attributes as $key => $value) {
            if ($key !== static::$primaryKey || $value !== null) {
                $attributes[$key] = $value;
            }
        }
        return $attributes;
    }

    protected function getDirtyAttributes()
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!isset($this->original[$key]) || $this->original[$key] !== $value) {
                if ($key !== static::$primaryKey) {
                    $dirty[$key] = $value;
                }
            }
        }
        return $dirty;
    }

    protected function syncOriginal()
    {
        $this->original = $this->attributes;
    }

    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute($key)
    {
        if (isset($this->attributes[$key])) {
            return $this->castAttribute($key, $this->attributes[$key]);
        }
        return null;
    }

    protected function castAttribute($key, $value)
    {
        if (!isset($this->casts[$key])) {
            return $value;
        }

        switch ($this->casts[$key]) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'double':
                return (float) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'string':
                return (string) $value;
            case 'array':
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }

    public function toArray()
    {
        $array = $this->attributes;
        
        foreach ($this->hidden as $key) {
            unset($array[$key]);
        }

        return $array;
    }

    public function toJson()
    {
        return json_encode($this->toArray());
    }

    public function __toString()
    {
        return $this->toJson();
    }
}
