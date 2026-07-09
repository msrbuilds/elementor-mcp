<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Traits;

/**
 * Trait for validating required fields in fromArray() methods.
 *
 * Use this trait in DTOs that need to validate required fields from input arrays.
 * Reports ALL missing fields at once for better developer experience.
 *
 * @mcp-version 2025-11-25
 */
trait ValidatesRequiredFields
{
    /**
     * Validates that all required fields are present in the data array.
     *
     * @param array<string, mixed> $data The input data array
     * @param string[] $requiredFields List of required field names
     * @return void
     * @throws \InvalidArgumentException If any required fields are missing
     */
    protected static function assertRequired(array $data, array $requiredFields): void
    {
        $missing = array_filter(
            $requiredFields,
            static fn(string $field): bool => !array_key_exists($field, $data)
        );
        
        if (count($missing) > 0) {
            throw new \InvalidArgumentException(sprintf(
                '%s: missing required field(s): %s',
                static::class,
                implode(', ', $missing)
            ));
        }
    }

    /**
     * Asserts a value is a string and returns it.
     *
     * @param mixed $value
     * @return string
     * @phpstan-assert string $value
     */
    protected static function asString($value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Expected string, got %s',
                gettype($value)
            ));
        }
        return $value;
    }

    /**
     * Asserts a value is an int and returns it.
     *
     * @param mixed $value
     * @return int
     * @phpstan-assert int $value
     */
    protected static function asInt($value): int
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Expected int, got %s',
                gettype($value)
            ));
        }
        return $value;
    }

    /**
     * Asserts a value is a float and returns it.
     *
     * @param mixed $value
     * @return float
     * @phpstan-assert float $value
     */
    protected static function asFloat($value): float
    {
        if (!is_float($value) && !is_int($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Expected float, got %s',
                gettype($value)
            ));
        }
        return (float) $value;
    }

    /**
     * Asserts a value is a bool and returns it.
     *
     * @param mixed $value
     * @return bool
     * @phpstan-assert bool $value
     */
    protected static function asBool($value): bool
    {
        if (!is_bool($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Expected bool, got %s',
                gettype($value)
            ));
        }
        return $value;
    }

    /**
     * Asserts a value is an array and returns it.
     *
     * @param mixed $value
     * @return array<string, mixed>
     * @phpstan-assert array<string, mixed> $value
     */
    protected static function asArray($value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Expected array, got %s',
                gettype($value)
            ));
        }
        /** @var array<string, mixed> */
        return $value;
    }

    /**
     * Returns a value as string or null.
     *
     * @param mixed $value
     * @return string|null
     */
    protected static function asStringOrNull($value): ?string
    {
        return $value === null ? null : self::asString($value);
    }

    /**
     * Returns a value as int or null.
     *
     * @param mixed $value
     * @return int|null
     */
    protected static function asIntOrNull($value): ?int
    {
        return $value === null ? null : self::asInt($value);
    }

    /**
     * Returns a value as float or null.
     *
     * @param mixed $value
     * @return float|null
     */
    protected static function asFloatOrNull($value): ?float
    {
        return $value === null ? null : self::asFloat($value);
    }

    /**
     * Returns a value as bool or null.
     *
     * @param mixed $value
     * @return bool|null
     */
    protected static function asBoolOrNull($value): ?bool
    {
        return $value === null ? null : self::asBool($value);
    }

    /**
     * Returns a value as array or null.
     *
     * @param mixed $value
     * @return array<string, mixed>|null
     */
    protected static function asArrayOrNull($value): ?array
    {
        return $value === null ? null : self::asArray($value);
    }

    /**
     * Asserts a value is an object and returns it.
     *
     * Accepts both PHP arrays and objects, auto-converting arrays to objects.
     * This aligns with MCP spec where JSON objects can be PHP arrays or objects.
     *
     * @param mixed $value
     * @return object
     * @phpstan-assert object $value
     */
    protected static function asObject($value): object
    {
        if (is_array($value)) {
            return (object) $value;
        }
        if (!is_object($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Expected array or object, got %s',
                gettype($value)
            ));
        }
        return $value;
    }

    /**
     * Returns a value as object or null.
     *
     * @param mixed $value
     * @return object|null
     */
    protected static function asObjectOrNull($value): ?object
    {
        return $value === null ? null : self::asObject($value);
    }

    /**
     * Asserts a value is an array of strings and returns it.
     *
     * @param mixed $value
     * @return array<int, string>
     */
    protected static function asStringArray($value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Expected array, got %s',
                gettype($value)
            ));
        }
        /** @var array<int, string> */
        return array_values(array_map(static fn($item): string => (string) $item, $value));
    }

    /**
     * Returns a value as array of strings or null.
     *
     * @param mixed $value
     * @return array<int, string>|null
     */
    protected static function asStringArrayOrNull($value): ?array
    {
        return $value === null ? null : self::asStringArray($value);
    }

    /**
     * Asserts a value is an associative array with string values only.
     *
     * Used for MCP types like { [key: string]: string } index signatures.
     *
     * @param mixed $value
     * @return array<string, string>
     * @phpstan-assert array<string, string> $value
     */
    protected static function asStringMap($value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Expected array, got %s',
                gettype($value)
            ));
        }
        foreach ($value as $key => $v) {
            if (!is_string($v)) {
                throw new \InvalidArgumentException(sprintf(
                    'Expected string value for key "%s", got %s',
                    (string) $key,
                    gettype($v)
                ));
            }
        }
        /** @var array<string, string> */
        return $value;
    }

    /**
     * Returns a value as string map or null.
     *
     * Used for optional MCP types like { [key: string]: string } | null.
     *
     * @param mixed $value
     * @return array<string, string>|null
     */
    protected static function asStringMapOrNull($value): ?array
    {
        return $value === null ? null : self::asStringMap($value);
    }

    /**
     * Asserts a value is an associative array with object values only.
     *
     * Used for MCP types like { [key: string]: object } index signatures.
     * Accepts both PHP arrays and objects as values, auto-converting arrays to objects.
     * This aligns with MCP spec where JSON objects can be PHP arrays or objects.
     *
     * @param mixed $value
     * @return array<string, object>
     * @phpstan-assert array<string, object> $value
     */
    protected static function asObjectMap($value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Expected array, got %s',
                gettype($value)
            ));
        }
        
        $result = [];
        foreach ($value as $key => $v) {
            if (is_array($v)) {
                $result[$key] = (object) $v;
            } elseif (is_object($v)) {
                $result[$key] = $v;
            } else {
                throw new \InvalidArgumentException(sprintf(
                    'Expected array or object for key "%s", got %s',
                    (string) $key,
                    gettype($v)
                ));
            }
        }
        
        /** @var array<string, object> */
        return $result;
    }

    /**
     * Returns a value as object map or null.
     *
     * Used for optional MCP types like { [key: string]: object } | null.
     *
     * @param mixed $value
     * @return array<string, object>|null
     */
    protected static function asObjectMapOrNull($value): ?array
    {
        return $value === null ? null : self::asObjectMap($value);
    }

    /**
     * Asserts a value is a scalar (string, int, float, or bool) for sprintf.
     *
     * @param mixed $value
     * @return string|int|float|bool
     */
    protected static function asScalar($value)
    {
        if (!is_scalar($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Expected scalar value, got %s',
                gettype($value)
            ));
        }
        return $value;
    }

    /**
     * Asserts a value is a string or number (int/float) and returns it.
     *
     * Used for MCP types like ProgressToken that accept string | number.
     *
     * @param mixed $value
     * @return string|int|float
     */
    protected static function asStringOrNumber($value)
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Expected string or number, got %s',
                gettype($value)
            ));
        }
        return $value;
    }

    /**
     * Returns a value as string or number (int/float), or null.
     *
     * Used for optional MCP types like ProgressToken that accept string | number | null.
     *
     * @param mixed $value
     * @return string|int|float|null
     */
    protected static function asStringOrNumberOrNull($value)
    {
        return $value === null ? null : self::asStringOrNumber($value);
    }
}
