# Collections

## Table of Contents

- [Introduction](#introduction)
- [Creating Collections](#creating-collections)
- [Available Methods](#available-methods)
- [Examples](#examples)

## Introduction

Collections provide a fluent, convenient wrapper for working with arrays of data. They offer dozens of methods for mapping, reducing, filtering, and manipulating data.

## Creating Collections

### Using the collect() Helper

```php
$collection = collect([1, 2, 3, 4, 5]);
```

### From Query Results

```php
$users = User::all(); // Returns a Collection
```

### Manual Instantiation

```php
use Core\Collection;

$collection = new Collection(['a', 'b', 'c']);
```

## Available Methods

### all()

Get all items in the collection:

```php
$collection = collect([1, 2, 3]);
$all = $collection->all(); // [1, 2, 3]
```

### count()

Count the items:

```php
$collection = collect([1, 2, 3, 4]);
$count = $collection->count(); // 4
```

### first() / last()

Get the first or last item:

```php
$collection = collect([1, 2, 3, 4, 5]);
$first = $collection->first(); // 1
$last = $collection->last(); // 5

// With callback
$first = $collection->first(function($value) {
    return $value > 2;
}); // 3
```

### map()

Transform each item:

```php
$collection = collect([1, 2, 3]);
$multiplied = $collection->map(function($item) {
    return $item * 2;
});
// [2, 4, 6]
```

### filter()

Filter items using a callback:

```php
$collection = collect([1, 2, 3, 4, 5]);
$filtered = $collection->filter(function($value) {
    return $value > 2;
});
// [3, 4, 5]
```

### where()

Filter items by key/value:

```php
$collection = collect([
    ['name' => 'John', 'age' => 30],
    ['name' => 'Jane', 'age' => 25],
    ['name' => 'Bob', 'age' => 30],
]);

$result = $collection->where('age', 30);
// [['name' => 'John', 'age' => 30], ['name' => 'Bob', 'age' => 30]]

$result = $collection->where('age', '>', 25);
// [['name' => 'John', 'age' => 30], ['name' => 'Bob', 'age' => 30]]
```

### pluck()

Extract a single column:

```php
$collection = collect([
    ['name' => 'John', 'age' => 30],
    ['name' => 'Jane', 'age' => 25],
]);

$names = $collection->pluck('name');
// ['John', 'Jane']

$names = $collection->pluck('name', 'age');
// [30 => 'John', 25 => 'Jane']
```

### reduce()

Reduce the collection to a single value:

```php
$collection = collect([1, 2, 3, 4]);
$sum = $collection->reduce(function($carry, $item) {
    return $carry + $item;
}, 0);
// 10
```

### each()

Iterate over items:

```php
$collection->each(function($item, $key) {
    echo "{$key}: {$item}\n";
});
```

### sort() / sortBy() / sortByDesc()

Sort the collection:

```php
$collection = collect([5, 3, 1, 2, 4]);
$sorted = $collection->sort();
// [1, 2, 3, 4, 5]

$collection = collect([
    ['name' => 'John', 'age' => 30],
    ['name' => 'Jane', 'age' => 25],
    ['name' => 'Bob', 'age' => 35],
]);

$sorted = $collection->sortBy('age');
// Sorted by age ascending

$sorted = $collection->sortByDesc('age');
// Sorted by age descending
```

### take()

Take the first/last n items:

```php
$collection = collect([1, 2, 3, 4, 5]);
$chunk = $collection->take(3);
// [1, 2, 3]

$chunk = $collection->take(-2);
// [4, 5]
```

### slice()

Slice the collection:

```php
$collection = collect([1, 2, 3, 4, 5, 6]);
$slice = $collection->slice(2, 3);
// [3, 4, 5]
```

### chunk()

Break into chunks:

```php
$collection = collect([1, 2, 3, 4, 5, 6, 7]);
$chunks = $collection->chunk(3);
// [[1, 2, 3], [4, 5, 6], [7]]
```

### flatten()

Flatten a multi-dimensional collection:

```php
$collection = collect([
    [1, 2, 3],
    [4, 5, 6],
    [7, 8, 9],
]);
$flattened = $collection->flatten();
// [1, 2, 3, 4, 5, 6, 7, 8, 9]
```

### unique()

Get unique items:

```php
$collection = collect([1, 2, 2, 3, 3, 4]);
$unique = $collection->unique();
// [1, 2, 3, 4]

// By key
$collection = collect([
    ['id' => 1, 'name' => 'John'],
    ['id' => 2, 'name' => 'Jane'],
    ['id' => 1, 'name' => 'John Doe'],
]);
$unique = $collection->unique('id');
// [['id' => 1, 'name' => 'John'], ['id' => 2, 'name' => 'Jane']]
```

### values() / keys()

Get values or keys:

```php
$collection = collect(['a' => 1, 'b' => 2, 'c' => 3]);
$values = $collection->values(); // [1, 2, 3]
$keys = $collection->keys(); // ['a', 'b', 'c']
```

### merge()

Merge with another collection/array:

```php
$collection = collect([1, 2, 3]);
$merged = $collection->merge([4, 5, 6]);
// [1, 2, 3, 4, 5, 6]
```

### groupBy()

Group items by a key:

```php
$collection = collect([
    ['type' => 'fruit', 'name' => 'apple'],
    ['type' => 'fruit', 'name' => 'banana'],
    ['type' => 'vegetable', 'name' => 'carrot'],
]);

$grouped = $collection->groupBy('type');
// [
//     'fruit' => [['type' => 'fruit', 'name' => 'apple'], ['type' => 'fruit', 'name' => 'banana']],
//     'vegetable' => [['type' => 'vegetable', 'name' => 'carrot']]
// ]
```

### isEmpty() / isNotEmpty()

Check if collection is empty:

```php
$collection = collect([]);
$collection->isEmpty(); // true
$collection->isNotEmpty(); // false
```

### toArray() / toJson()

Convert to array or JSON:

```php
$collection = collect([1, 2, 3]);
$array = $collection->toArray(); // [1, 2, 3]
$json = $collection->toJson(); // "[1,2,3]"
```

## Examples

### Working with User Data

```php
$users = User::all();

// Get all user emails
$emails = $users->pluck('email');

// Get active users
$active = $users->filter(function($user) {
    return $user->status === 'active';
});

// Get user names grouped by role
$byRole = $users->groupBy('role')->map(function($group) {
    return $group->pluck('name');
});
```

### Data Transformation

```php
$products = collect([
    ['name' => 'Laptop', 'price' => 1000, 'tax' => 0.1],
    ['name' => 'Mouse', 'price' => 50, 'tax' => 0.1],
    ['name' => 'Keyboard', 'price' => 100, 'tax' => 0.1],
]);

// Calculate total prices with tax
$totals = $products->map(function($product) {
    return [
        'name' => $product['name'],
        'total' => $product['price'] * (1 + $product['tax'])
    ];
});

// Get total revenue
$revenue = $products->reduce(function($carry, $product) {
    return $carry + ($product['price'] * (1 + $product['tax']));
}, 0);
```

### Pagination

```php
$items = collect(range(1, 100));

// Simple pagination
$perPage = 10;
$page = 2;
$paginated = $items->slice(($page - 1) * $perPage, $perPage);
```

### Advanced Filtering

```php
$orders = collect([
    ['id' => 1, 'status' => 'completed', 'total' => 100],
    ['id' => 2, 'status' => 'pending', 'total' => 200],
    ['id' => 3, 'status' => 'completed', 'total' => 150],
    ['id' => 4, 'status' => 'completed', 'total' => 50],
]);

// Get completed orders over $75
$filtered = $orders
    ->where('status', 'completed')
    ->where('total', '>', 75)
    ->sortByDesc('total');
```

### Nested Collections

```php
$departments = collect([
    [
        'name' => 'Sales',
        'employees' => collect([
            ['name' => 'John', 'salary' => 50000],
            ['name' => 'Jane', 'salary' => 60000],
        ])
    ],
    [
        'name' => 'IT',
        'employees' => collect([
            ['name' => 'Bob', 'salary' => 70000],
            ['name' => 'Alice', 'salary' => 80000],
        ])
    ]
]);

// Get all employees
$allEmployees = $departments->pluck('employees')->flatten(1);

// Get total payroll
$payroll = $departments->reduce(function($carry, $dept) {
    return $carry + $dept['employees']->sum('salary');
}, 0);
```

## Method Chaining

Collections are fluent, allowing you to chain methods:

```php
$result = collect($data)
    ->filter(function($item) {
        return $item['active'];
    })
    ->sortBy('created_at')
    ->take(10)
    ->pluck('name')
    ->toArray();
```

## Tips

- Collections are immutable - methods return new collections
- Use `values()` after `filter()` to reset array keys
- Collections implement ArrayAccess, so you can use array syntax
- Many methods accept dot notation for nested arrays
- Collections are perfect for API response transformation
