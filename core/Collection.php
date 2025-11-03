<?php

namespace Core;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;

class Collection implements ArrayAccess, Countable, IteratorAggregate
{
    protected $items = [];

    public function __construct($items = [])
    {
        $this->items = $this->getArrayableItems($items);
    }

    public static function make($items = [])
    {
        return new static($items);
    }

    public function all()
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty()
    {
        return empty($this->items);
    }

    public function isNotEmpty()
    {
        return !$this->isEmpty();
    }

    public function first($callback = null, $default = null)
    {
        if (is_null($callback)) {
            return empty($this->items) ? $default : reset($this->items);
        }

        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    public function last($callback = null, $default = null)
    {
        if (is_null($callback)) {
            return empty($this->items) ? $default : end($this->items);
        }

        return $this->reverse()->first($callback, $default);
    }

    public function map(callable $callback)
    {
        $keys = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);
        
        return new static(array_combine($keys, $items));
    }

    public function filter($callback = null)
    {
        if ($callback) {
            return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
        }

        return new static(array_filter($this->items));
    }

    public function where($key, $operator = null, $value = null)
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->filter(function ($item) use ($key, $operator, $value) {
            $retrieved = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);

            switch ($operator) {
                case '=':
                case '==':
                    return $retrieved == $value;
                case '!=':
                case '<>':
                    return $retrieved != $value;
                case '<':
                    return $retrieved < $value;
                case '>':
                    return $retrieved > $value;
                case '<=':
                    return $retrieved <= $value;
                case '>=':
                    return $retrieved >= $value;
                case '===':
                    return $retrieved === $value;
                case '!==':
                    return $retrieved !== $value;
            }

            return true;
        });
    }

    public function pluck($value, $key = null)
    {
        $results = [];

        foreach ($this->items as $item) {
            $itemValue = is_array($item) ? ($item[$value] ?? null) : ($item->$value ?? null);

            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
                $results[$itemKey] = $itemValue;
            }
        }

        return new static($results);
    }

    public function reject(callable $callback)
    {
        return $this->filter(function ($value, $key) use ($callback) {
            return !$callback($value, $key);
        });
    }

    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function each(callable $callback)
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    public function reverse()
    {
        return new static(array_reverse($this->items, true));
    }

    public function sort($callback = null)
    {
        $items = $this->items;

        $callback
            ? uasort($items, $callback)
            : asort($items);

        return new static($items);
    }

    public function sortBy($callback, $options = SORT_REGULAR, $descending = false)
    {
        $results = [];

        $callback = $this->valueRetriever($callback);

        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        $descending ? arsort($results, $options) : asort($results, $options);

        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    public function sortByDesc($callback, $options = SORT_REGULAR)
    {
        return $this->sortBy($callback, $options, true);
    }

    public function take($limit)
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    public function slice($offset, $length = null)
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    public function chunk($size)
    {
        $chunks = [];

        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    public function flatten($depth = INF)
    {
        return new static($this->flattenArray($this->items, $depth));
    }

    protected function flattenArray($array, $depth)
    {
        $result = [];

        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1) {
                $result = array_merge($result, array_values($item));
            } else {
                $result = array_merge($result, $this->flattenArray($item, $depth - 1));
            }
        }

        return $result;
    }

    public function unique($key = null)
    {
        if (is_null($key)) {
            return new static(array_unique($this->items, SORT_REGULAR));
        }

        $exists = [];
        $results = [];

        foreach ($this->items as $item) {
            $value = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);

            if (!in_array($value, $exists)) {
                $exists[] = $value;
                $results[] = $item;
            }
        }

        return new static($results);
    }

    public function values()
    {
        return new static(array_values($this->items));
    }

    public function keys()
    {
        return new static(array_keys($this->items));
    }

    public function merge($items)
    {
        return new static(array_merge($this->items, $this->getArrayableItems($items)));
    }

    public function union($items)
    {
        return new static($this->items + $this->getArrayableItems($items));
    }

    public function groupBy($groupBy)
    {
        $groupBy = $this->valueRetriever($groupBy);
        $results = [];

        foreach ($this->items as $key => $value) {
            $groupKeys = $groupBy($value, $key);

            if (!is_array($groupKeys)) {
                $groupKeys = [$groupKeys];
            }

            foreach ($groupKeys as $groupKey) {
                if (!array_key_exists($groupKey, $results)) {
                    $results[$groupKey] = new static();
                }

                $results[$groupKey]->items[] = $value;
            }
        }

        return new static($results);
    }

    public function toArray()
    {
        return array_map(function ($value) {
            return $value instanceof self ? $value->toArray() : $value;
        }, $this->items);
    }

    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    public function __toString()
    {
        return $this->toJson();
    }

    public function getIterator(): \Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function offsetExists($key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function offsetGet($key): mixed
    {
        return $this->items[$key];
    }

    public function offsetSet($key, $value): void
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    public function offsetUnset($key): void
    {
        unset($this->items[$key]);
    }

    protected function getArrayableItems($items)
    {
        if (is_array($items)) {
            return $items;
        } elseif ($items instanceof self) {
            return $items->all();
        } elseif ($items instanceof \Traversable) {
            return iterator_to_array($items);
        }

        return (array) $items;
    }

    protected function valueRetriever($value)
    {
        if (is_callable($value)) {
            return $value;
        }

        return function ($item) use ($value) {
            return is_array($item) ? ($item[$value] ?? null) : ($item->$value ?? null);
        };
    }
}
