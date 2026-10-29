<?php

declare(strict_types=1);

namespace VtPhp\OpenApi;

/**
 * Turns form request rules ('required|email|max:255') into a JSON Schema
 * object, the shape Swagger shows for a request body or query string.
 * Dotted keys nest: 'address.city' is a property of an `address` object and
 * 'tags.*' describes the items of a `tags` array.
 */
final class RulesSchema
{
    /**
     * @param array<string, array<int, mixed>|string> $rules
     * @return array<string, mixed>
     */
    public static function fromRules(array $rules): array
    {
        $root = ['type' => 'object', 'properties' => []];

        foreach ($rules as $field => $fieldRules) {
            $parsed = self::parse($fieldRules);
            self::insert($root, explode('.', (string) $field), self::fieldSchema($parsed), array_key_exists('required', $parsed));

            if (array_key_exists('confirmed', $parsed) && !str_contains((string) $field, '.')) {
                $confirmation = self::fieldSchema(array_diff_key($parsed, ['confirmed' => true, 'unique' => true, 'exists' => true]));
                $confirmation['description'] = "Must match {$field}.";
                self::insert($root, ["{$field}_confirmation"], $confirmation, array_key_exists('required', $parsed));
            }
        }

        return $root;
    }

    /**
     * @param array<int, mixed>|string $rules
     * @return array<string, ?string> rule name => parameter
     */
    public static function parse(array|string $rules): array
    {
        $list = is_string($rules) ? explode('|', $rules) : $rules;
        $parsed = [];

        foreach ($list as $rule) {
            if (!is_string($rule) || $rule === '') {
                continue; // rule objects/closures can't be described
            }

            [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
            $parsed[strtolower(trim($name))] = $param;
        }

        return $parsed;
    }

    /**
     * @param array<string, ?string> $rules
     * @return array<string, mixed>
     */
    public static function fieldSchema(array $rules): array
    {
        $type = match (true) {
            array_key_exists('integer', $rules), array_key_exists('int', $rules) => 'integer',
            array_key_exists('numeric', $rules) => 'number',
            array_key_exists('boolean', $rules), array_key_exists('bool', $rules) => 'boolean',
            array_key_exists('array', $rules) => 'array',
            default => 'string',
        };

        $schema = ['type' => $type];
        $notes = [];

        if ($type === 'array') {
            $schema['items'] = new \stdClass(); // any value, until a `field.*` rule says otherwise
        }

        if (array_key_exists('email', $rules)) {
            $schema['format'] = 'email';
        }

        if (array_key_exists('date', $rules)) {
            $schema['format'] = 'date-time';
        }

        if (isset($rules['in'])) {
            $schema['enum'] = explode(',', $rules['in']);
        }

        foreach (['min' => ['minLength', 'minimum', 'minItems'], 'max' => ['maxLength', 'maximum', 'maxItems']] as $rule => [$string, $number, $array]) {
            if (isset($rules[$rule]) && is_numeric($rules[$rule])) {
                $value = $rules[$rule] + 0;
                $schema[match ($type) {
                    'integer', 'number' => $number,
                    'array' => $array,
                    default => $string,
                }] = $value;
            }
        }

        if (isset($rules['max_bytes'])) {
            $notes[] = "At most {$rules['max_bytes']} bytes (accented letters and emoji take more than one).";
        }

        if (isset($rules['unique'])) {
            $notes[] = 'Must not already be taken.';
        }

        if (isset($rules['exists'])) {
            $notes[] = 'Must match an existing record.';
        }

        if (array_key_exists('confirmed', $rules)) {
            $notes[] = 'Send the same value again as the matching *_confirmation field.';
        }

        if (array_key_exists('nullable', $rules)) {
            $schema['type'] = [$schema['type'], 'null'];
        }

        if ($notes !== []) {
            $schema['description'] = implode(' ', $notes);
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $node
     * @param list<string> $path
     * @param array<string, mixed> $schema
     */
    private static function insert(array &$node, array $path, array $schema, bool $required): void
    {
        $key = array_shift($path);

        if ($key === '*') {
            $node['type'] = 'array';
            unset($node['properties']);

            if ($path === []) {
                $node['items'] = $schema;

                return;
            }

            if (!isset($node['items']) || !is_array($node['items'])) {
                $node['items'] = ['type' => 'object', 'properties' => []];
            }

            self::insert($node['items'], $path, $schema, $required);

            return;
        }

        if (!isset($node['properties']) || !is_array($node['properties'])) {
            $node['type'] = 'object';
            $node['properties'] = [];
        }

        if ($path === []) {
            // Keep children that a more specific rule (e.g. 'tags.*') added first.
            $existing = $node['properties'][$key] ?? [];
            $node['properties'][$key] = array_merge($schema, array_intersect_key(is_array($existing) ? $existing : [], ['items' => 1, 'properties' => 1, 'required' => 1]));
        } else {
            $node['properties'][$key] ??= ['type' => 'object', 'properties' => []];
            self::insert($node['properties'][$key], $path, $schema, $required);
        }

        if ($required && $path === []) {
            $node['required'] = array_values(array_unique([...($node['required'] ?? []), $key]));
        }
    }
}
