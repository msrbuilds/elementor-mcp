<?php

declare(strict_types=1);

namespace WP\McpSchema\Common;

/**
 * Base class for all Data Transfer Objects.
 *
 * @mcp-version 2025-11-25
 */
abstract class AbstractDataTransferObject
{
    /**
     * Creates an instance from an array.
     *
     * @param array<string, mixed> $data
     * @return static
     */
    abstract public static function fromArray(array $data): self;

    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * Converts the instance to JSON.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Creates an instance from JSON.
     *
     * @param string $json
     * @return static
     */
    public static function fromJson(string $json): self
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return static::fromArray($data);
    }
}
