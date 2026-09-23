<?php

declare(strict_types=1);

namespace VtPhp\Validation;

use VtPhp\Exceptions\ValidationException;

/**
 * Lightweight rule-string validator ("required|string|max:100"), inspired by
 * Laravel's validation DX but with no external dependency.
 */
final class Validator
{
    /** @var array<string, array<int, string>> */
    private array $errors = [];

    private bool $ran = false;

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<int, string>|string> $rules
     */
    private function __construct(private readonly array $data, private readonly array $rules)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<int, string>|string> $rules
     */
    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function fails(): bool
    {
        $this->run();

        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        $this->run();

        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }

        return $this->data;
    }

    private function run(): void
    {
        if ($this->ran) {
            return;
        }

        $this->ran = true;

        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $this->data[$field] ?? null;

            if ($value === null && in_array('nullable', $rules, true)) {
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }

                $this->applyRule((string) $field, $value, $rule);
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

        $valid = match ($name) {
            'required' => $value !== null && $value !== '',
            'string' => is_string($value),
            'integer', 'int' => is_int($value) || (is_string($value) && ctype_digit($value)),
            'numeric' => is_numeric($value),
            'boolean', 'bool' => is_bool($value) || in_array($value, [0, 1, '0', '1', true, false], true),
            'array' => is_array($value),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'max' => is_string($value) ? mb_strlen($value) <= (int) $param : (!is_numeric($value) || $value <= (int) $param),
            'min' => is_string($value) ? mb_strlen($value) >= (int) $param : (!is_numeric($value) || $value >= (int) $param),
            'in' => in_array((string) $value, explode(',', (string) $param), true),
            'date' => is_string($value) && strtotime($value) !== false,
            'confirmed' => $value === ($this->data[$field.'_confirmation'] ?? null),
            default => true,
        };

        if (!$valid) {
            $this->errors[$field][] = $this->message($field, $name, $param);
        }
    }

    private function message(string $field, ?string $rule, ?string $param): string
    {
        return match ($rule) {
            'required' => "The {$field} field is required.",
            'string' => "The {$field} field must be a string.",
            'integer', 'int' => "The {$field} field must be an integer.",
            'numeric' => "The {$field} field must be numeric.",
            'boolean', 'bool' => "The {$field} field must be a boolean.",
            'array' => "The {$field} field must be an array.",
            'email' => "The {$field} field must be a valid email address.",
            'max' => "The {$field} field must not be greater than {$param}.",
            'min' => "The {$field} field must be at least {$param}.",
            'in' => "The selected {$field} is invalid.",
            'date' => "The {$field} field must be a valid date.",
            'confirmed' => "The {$field} confirmation does not match.",
            default => "The {$field} field is invalid.",
        };
    }
}
