<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Contracts;

/**
 * Interface for objects that provide their JSON Schema definition.
 *
 * @mcp-version 2025-11-25
 */
interface WithJsonSchemaInterface
{
    /**
     * Returns the JSON Schema definition for this type.
     *
     * @return array<string, mixed> The JSON Schema definition.
     */
    public static function getJsonSchema(): array;
}
