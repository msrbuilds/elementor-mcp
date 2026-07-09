<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\Union;

/**
 * Restricted schema definitions that only allow primitive types
 * without nested objects or arrays.
 *
 * Union type members:
 * - StringSchema
 * - NumberSchema
 * - BooleanSchema
 * - EnumSchema
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
interface PrimitiveSchemaDefinitionInterface
{
    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
