<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\Factory;

use WP\McpSchema\Client\Elicitation\Union\MultiSelectEnumSchemaInterface;
use WP\McpSchema\Client\Elicitation\DTO\UntitledMultiSelectEnumSchema;
use WP\McpSchema\Client\Elicitation\DTO\TitledMultiSelectEnumSchema;

/**
 * Factory for creating MultiSelectEnumSchema union type instances.
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
final class MultiSelectEnumSchemaFactory
{
    /**
     * Registry mapping discriminator values to implementation classes.
     *
     * @var array<string, class-string<MultiSelectEnumSchemaInterface>>
     */
    public const REGISTRY = [
        'array' => UntitledMultiSelectEnumSchema::class,
    ];

    /**
     * Creates an instance from an array.
     *
     * @param array<string, mixed> $data
     * @return MultiSelectEnumSchemaInterface
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): MultiSelectEnumSchemaInterface
    {
        if (!isset($data['type'])) {
            throw new \InvalidArgumentException('Missing discriminator field: type');
        }

        switch ($data['type']) {
            case 'array':
                return UntitledMultiSelectEnumSchema::fromArray($data);
            default:
                throw new \InvalidArgumentException(sprintf(
                    "Unknown type value '%s'. Valid values: %s",
                    is_scalar($data['type']) ? $data['type'] : gettype($data['type']),
                    implode(', ', array_keys(self::REGISTRY))
                ));
        }
    }

    /**
     * Checks if a type value is supported by this factory.
     *
     * @param string $type
     * @return bool
     */
    public static function supports(string $type): bool
    {
        return isset(self::REGISTRY[$type]);
    }

    /**
     * Returns all supported type values.
     *
     * @return array<string>
     */
    public static function types(): array
    {
        return array_keys(self::REGISTRY);
    }
}
