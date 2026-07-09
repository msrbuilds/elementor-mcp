<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;

/**
 * Schema for array items with enum options and display labels.
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
class TitledMultiSelectEnumSchemaItems extends AbstractDataTransferObject
{
    /**
     */
    public function __construct()
    {
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self();
    }

    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        return $result;
    }
}
