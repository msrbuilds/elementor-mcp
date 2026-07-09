<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\Union;

use WP\McpSchema\Client\Elicitation\Union\PrimitiveSchemaDefinitionInterface;

/**
 * Union type members:
 * - SingleSelectEnumSchema
 * - MultiSelectEnumSchema
 * - LegacyTitledEnumSchema
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
interface EnumSchemaInterface extends PrimitiveSchemaDefinitionInterface
{
}
