<?php

namespace Core;

class Validator
{
    protected $errors = [];
    protected $data = [];
    protected $rules = [];

    public function validate($data, $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            foreach ($fieldRules as $rule) {
                $this->validateField($field, $rule);
            }
        }

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return $this->data;
    }

    protected function validateField($field, $rule)
    {
        // Parse rule and parameters
        $parameters = [];
        if (strpos($rule, ':') !== false) {
            [$rule, $paramString] = explode(':', $rule, 2);
            $parameters = explode(',', $paramString);
        }

        $value = $this->data[$field] ?? null;

        $method = 'validate' . str_replace('_', '', ucwords($rule, '_'));

        if (method_exists($this, $method)) {
            $this->$method($field, $value, $parameters);
        }
    }

    protected function validateRequired($field, $value, $parameters)
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, "The {$field} field is required.");
        }
    }

    protected function validateEmail($field, $value, $parameters)
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "The {$field} must be a valid email address.");
        }
    }

    protected function validateMin($field, $value, $parameters)
    {
        $min = $parameters[0] ?? 0;

        if (is_string($value) && strlen($value) < $min) {
            $this->addError($field, "The {$field} must be at least {$min} characters.");
        } elseif (is_numeric($value) && $value < $min) {
            $this->addError($field, "The {$field} must be at least {$min}.");
        } elseif (is_array($value) && count($value) < $min) {
            $this->addError($field, "The {$field} must have at least {$min} items.");
        }
    }

    protected function validateMax($field, $value, $parameters)
    {
        $max = $parameters[0] ?? 0;

        if (is_string($value) && strlen($value) > $max) {
            $this->addError($field, "The {$field} may not be greater than {$max} characters.");
        } elseif (is_numeric($value) && $value > $max) {
            $this->addError($field, "The {$field} may not be greater than {$max}.");
        } elseif (is_array($value) && count($value) > $max) {
            $this->addError($field, "The {$field} may not have more than {$max} items.");
        }
    }

    protected function validateNumeric($field, $value, $parameters)
    {
        if ($value && !is_numeric($value)) {
            $this->addError($field, "The {$field} must be a number.");
        }
    }

    protected function validateInteger($field, $value, $parameters)
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_INT) && $value !== 0) {
            $this->addError($field, "The {$field} must be an integer.");
        }
    }

    protected function validateString($field, $value, $parameters)
    {
        if ($value && !is_string($value)) {
            $this->addError($field, "The {$field} must be a string.");
        }
    }

    protected function validateArray($field, $value, $parameters)
    {
        if ($value && !is_array($value)) {
            $this->addError($field, "The {$field} must be an array.");
        }
    }

    protected function validateBoolean($field, $value, $parameters)
    {
        if ($value !== null && !is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true)) {
            $this->addError($field, "The {$field} field must be true or false.");
        }
    }

    protected function validateUrl($field, $value, $parameters)
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, "The {$field} format is invalid.");
        }
    }

    protected function validateIn($field, $value, $parameters)
    {
        if ($value && !in_array($value, $parameters)) {
            $list = implode(', ', $parameters);
            $this->addError($field, "The selected {$field} is invalid. Must be one of: {$list}");
        }
    }

    protected function validateConfirmed($field, $value, $parameters)
    {
        $confirmField = $field . '_confirmation';
        if ($value !== ($this->data[$confirmField] ?? null)) {
            $this->addError($field, "The {$field} confirmation does not match.");
        }
    }

    protected function validateSame($field, $value, $parameters)
    {
        $otherField = $parameters[0] ?? null;
        if ($value !== ($this->data[$otherField] ?? null)) {
            $this->addError($field, "The {$field} and {$otherField} must match.");
        }
    }

    protected function validateDifferent($field, $value, $parameters)
    {
        $otherField = $parameters[0] ?? null;
        if ($value === ($this->data[$otherField] ?? null)) {
            $this->addError($field, "The {$field} and {$otherField} must be different.");
        }
    }

    protected function validateUnique($field, $value, $parameters)
    {
        if (!$value) return;

        $table = $parameters[0] ?? null;
        $column = $parameters[1] ?? $field;
        $ignoreId = $parameters[2] ?? null;

        if (!$table) {
            $this->addError($field, "Unique validation requires a table name.");
            return;
        }

        $db = Database::getInstance();
        $query = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?";
        $bindings = [$value];

        if ($ignoreId) {
            $query .= " AND id != ?";
            $bindings[] = $ignoreId;
        }

        $result = $db->selectOne($query, $bindings);
        
        if ($result && $result['count'] > 0) {
            $this->addError($field, "The {$field} has already been taken.");
        }
    }

    protected function validateExists($field, $value, $parameters)
    {
        if (!$value) return;

        $table = $parameters[0] ?? null;
        $column = $parameters[1] ?? $field;

        if (!$table) {
            $this->addError($field, "Exists validation requires a table name.");
            return;
        }

        $db = Database::getInstance();
        $result = $db->selectOne("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?", [$value]);
        
        if (!$result || $result['count'] == 0) {
            $this->addError($field, "The selected {$field} is invalid.");
        }
    }

    protected function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    public function errors()
    {
        return $this->errors;
    }
}

class ValidationException extends \Exception
{
    protected $errors;

    public function __construct($errors)
    {
        $this->errors = $errors;
        parent::__construct("Validation failed");
    }

    public function errors()
    {
        return $this->errors;
    }
}
