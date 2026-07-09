<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\Factory;

use WP\McpSchema\Client\Elicitation\Union\PrimitiveSchemaDefinitionInterface;
use WP\McpSchema\Client\Elicitation\DTO\StringSchema;
use WP\McpSchema\Client\Elicitation\DTO\NumberSchema;
use WP\McpSchema\Client\Elicitation\DTO\BooleanSchema;
use WP\McpSchema\Client\Elicitation\Factory\EnumSchemaFactory;

/**
 * Factory for creating PrimitiveSchemaDefinition union type instances.
 *
 * Restricted schema definitions that only allow primitive types
 * without nested objects or arrays.
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
final class PrimitiveSchemaDefinitionFactory
{
    /**
     * Registry mapping discriminator values to implementation classes.
     * Note: Some values route to other factories for nested union resolution.
     *
     * @var array<string, class-string>
     */
    public const REGISTRY = [
        'number' => NumberSchema::class,
        'integer' => NumberSchema::class,
        'boolean' => BooleanSchema::class,
        'array' => EnumSchemaFactory::class,
        'string' => StringSchema::class,
    ];

    /**
     * Creates an instance from an array.
     *
     * @param array<string, mixed> $data
     * @return PrimitiveSchemaDefinitionInterface
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): PrimitiveSchemaDefinitionInterface
    {
        if (!isset($data['type'])) {
            throw new \InvalidArgumentException('Missing discriminator field: type');
        }

        switch ($data['type']) {
            case 'number':
                return NumberSchema::fromArray($data);
            case 'integer':
                return NumberSchema::fromArray($data);
            case 'boolean':
                return BooleanSchema::fromArray($data);
            case 'array':
                return EnumSchemaFactory::fromArray($data);
            case 'string':
                if (isset($data['minLength'])) {
                    return StringSchema::fromArray($data);
                }
                elseif (isset($data['enum'])) {
                    return EnumSchemaFactory::fromArray($data);
                }
                else {
                    return StringSchema::fromArray($data);
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
