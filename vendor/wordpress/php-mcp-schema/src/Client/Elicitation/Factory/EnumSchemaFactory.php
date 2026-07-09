<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\Factory;

use WP\McpSchema\Client\Elicitation\Union\EnumSchemaInterface;
use WP\McpSchema\Client\Elicitation\Factory\SingleSelectEnumSchemaFactory;
use WP\McpSchema\Client\Elicitation\Factory\MultiSelectEnumSchemaFactory;
use WP\McpSchema\Client\Elicitation\DTO\LegacyTitledEnumSchema;

/**
 * Factory for creating EnumSchema union type instances.
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
final class EnumSchemaFactory
{
    /**
     * Registry mapping discriminator values to implementation classes.
     * Note: Some values route to other factories for nested union resolution.
     *
     * @var array<string, class-string>
     */
    public const REGISTRY = [
        'array' => MultiSelectEnumSchemaFactory::class,
        'string' => LegacyTitledEnumSchema::class,
    ];

    /**
     * Creates an instance from an array.
     *
     * @param array<string, mixed> $data
     * @return EnumSchemaInterface
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): EnumSchemaInterface
    {
        if (!isset($data['type'])) {
            throw new \InvalidArgumentException('Missing discriminator field: type');
        }

        switch ($data['type']) {
            case 'array':
                return MultiSelectEnumSchemaFactory::fromArray($data);
            case 'string':
                if (isset($data['oneOf'])) {
                    return SingleSelectEnumSchemaFactory::fromArray($data);
                }
                elseif (isset($data['enumNames'])) {
                    return LegacyTitledEnumSchema::fromArray($data);
                }
                else {
                    return LegacyTitledEnumSchema::fromArray($data);
                }
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
