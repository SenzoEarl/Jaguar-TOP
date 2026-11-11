<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ValidationException;
use DateTimeImmutable;

/**
 * Central declarative input validator.
 *
 * Keeps controllers thin by normalizing and validating user inputs against
 * explicit pipe-delimited rules (`required|string|min:3|max:120`) and throwing
 * ValidationException with safe field-level messages.
 */
final class Validator
{
    /**
     * Validate `$data` against `$rules` and return the cleaned subset of fields.
     *
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @return array<string, mixed>
     */
    public function validate(array $data, array $rules): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $rawRules) {
            $ruleList = is_array($rawRules)
                ? $rawRules
                : array_values(array_filter(explode('|', $rawRules), static fn (string $r): bool => $r !== ''));

            $value = $data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }

            $isRequired = in_array('required', $ruleList, true);
            $isNullable = in_array('nullable', $ruleList, true);
            $isEmpty = $value === null || $value === '';

            if ($isEmpty) {
                if ($isRequired) {
                    $errors[$field][] = sprintf('The %s field is required.', $this->humanizeField($field));
                } elseif ($isNullable || !$isRequired) {
                    $validated[$field] = null;
                }
                continue;
            }

            foreach ($ruleList as $rule) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }

                $error = $this->evaluateRule($field, $value, $rule, $data);
                if ($error !== null) {
                    $errors[$field][] = $error;
                }
            }

            if (!isset($errors[$field])) {
                $validated[$field] = $this->castValue($value, $ruleList);
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $validated;
    }

    /**
     * @param array<string, mixed> $allData
     */
    private function evaluateRule(string $field, mixed $value, string $rule, array $allData): ?string
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, '');
        $label = $this->humanizeField($field);

        return match ($name) {
            'string' => is_string($value) ? null : sprintf('The %s must be text.', $label),
            'int' => filter_var($value, FILTER_VALIDATE_INT) !== false
                ? null
                : sprintf('The %s must be a whole number.', $label),
            'numeric' => is_numeric($value)
                ? null
                : sprintf('The %s must be a number.', $label),
            'email' => filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false
                ? null
                : sprintf('The %s must be a valid email address.', $label),
            'min' => $this->checkMin($label, $value, (int) $param),
            'max' => $this->checkMax($label, $value, (int) $param),
            'in' => in_array((string) $value, explode(',', $param), true)
                ? null
                : sprintf('The selected %s is invalid.', $label),
            'date' => $this->isValidIsoDate((string) $value)
                ? null
                : sprintf('The %s must be a valid YYYY-MM-DD date.', $label),
            'after_field' => $this->checkAfterField($label, (string) $value, $param, $allData),
            'after_or_equal_today' => $this->checkNotPastDate($label, (string) $value),
            'username' => preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', (string) $value) === 1
                ? null
                : sprintf(
                    'The %s may only contain letters, numbers, dots, underscores, or hyphens (3-60 chars).',
                    $label
                ),
            'slug' => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value) === 1
                ? null
                : sprintf('The %s format is invalid.', $label),
            'same' => (string) $value === trim((string) ($allData[$param] ?? ''))
                ? null
                : sprintf('The %s must match %s.', $label, $this->humanizeField($param)),
            default => null,
        };
    }

    private function checkMin(string $label, mixed $value, int $min): ?string
    {
        $isIntString = is_string($value)
            && filter_var($value, FILTER_VALIDATE_INT) !== false
            && !str_contains($value, '.');
        if (is_int($value) || $isIntString) {
            // Note: when validating strings with `min`, check length unless strictly numeric.
            if (is_int($value)) {
                return $value >= $min ? null : sprintf('The %s must be at least %d.', $label, $min);
            }
        }

        if (mb_strlen((string) $value) < $min) {
            return sprintf('The %s must be at least %d characters.', $label, $min);
        }

        return null;
    }

    private function checkMax(string $label, mixed $value, int $max): ?string
    {
        if (is_int($value)) {
            return $value <= $max ? null : sprintf('The %s may not exceed %d.', $label, $max);
        }

        if (mb_strlen((string) $value) > $max) {
            return sprintf('The %s may not exceed %d characters.', $label, $max);
        }

        return null;
    }

    private function isValidIsoDate(string $value): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }

    /**
     * @param array<string, mixed> $allData
     */
    private function checkAfterField(string $label, string $value, string $otherField, array $allData): ?string
    {
        $otherValue = trim((string) ($allData[$otherField] ?? ''));
        if (!$this->isValidIsoDate($value) || !$this->isValidIsoDate($otherValue)) {
            return null;
        }

        if ($value <= $otherValue) {
            return sprintf('The %s must be after %s.', $label, $this->humanizeField($otherField));
        }

        return null;
    }

    private function checkNotPastDate(string $label, string $value): ?string
    {
        if (!$this->isValidIsoDate($value)) {
            return null;
        }

        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        if ($value < $today) {
            return sprintf('The %s cannot be in the past.', $label);
        }

        return null;
    }

    /**
     * @param list<string> $rules
     */
    private function castValue(mixed $value, array $rules): mixed
    {
        if (in_array('int', $rules, true)) {
            return (int) $value;
        }

        if (in_array('numeric', $rules, true)) {
            return (float) $value;
        }

        return (string) $value;
    }

    private function humanizeField(string $field): string
    {
        return str_replace('_', ' ', $field);
    }
}
