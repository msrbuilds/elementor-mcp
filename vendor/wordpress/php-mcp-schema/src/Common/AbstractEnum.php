<?php

declare(strict_types=1);

namespace WP\McpSchema\Common;

/**
 * Base class for PHP 7.4 enum emulation.
 *
 * @mcp-version 2025-11-25
 */
abstract class AbstractEnum
{
    /** @var string */
    private string $value;

    /** @var array<string, static> */
    private static array $instances = [];

    /**
     * @param string $value
     */
    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates an instance from a value.
     *
     * @param string $value
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function from(string $value): self
    {
        $values = static::values();
        if (!in_array($value, $values, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid enum value: %s. Valid values: %s', $value, implode(', ', $values))
            );
        }

        $key = static::class . '::' . $value;
        if (!isset(self::$instances[$key])) {
            /** @phpstan-ignore new.static (Intentional: private constructor prevents subclass override) */
            self::$instances[$key] = new static($value);
        }

        return self::$instances[$key];
    }

    /**
     * Creates an instance from a value, or null if invalid.
     *
     * @param string $value
     * @return static|null
     */
    public static function tryFrom(string $value): ?self
    {
        try {
            return static::from($value);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * Returns all valid values for this enum.
     *
     * @return string[]
     */
    abstract public static function values(): array;

    /**
     * Returns all cases as instances.
     *
     * @return static[]
     */
    public static function cases(): array
    {
        return array_map(fn(string $value) => static::from($value), static::values());
    }

    /**
     * Gets the enum value.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Converts to string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Compares with another instance.
     *
     * @param self $other
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value && static::class === get_class($other);
    }
}
