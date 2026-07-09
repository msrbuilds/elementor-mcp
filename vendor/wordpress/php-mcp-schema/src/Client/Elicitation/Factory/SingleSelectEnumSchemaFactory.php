<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\Factory;

use WP\McpSchema\Client\Elicitation\Union\SingleSelectEnumSchemaInterface;
use WP\McpSchema\Client\Elicitation\DTO\UntitledSingleSelectEnumSchema;
use WP\McpSchema\Client\Elicitation\DTO\TitledSingleSelectEnumSchema;

/**
 * Factory for creating SingleSelectEnumSchema union type instances.
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
final class SingleSelectEnumSchemaFactory
{
    /**
     * Registry mapping discriminator values to implementation classes.
     *
     * @var array<string, class-string<SingleSelectEnumSchemaInterface>>
     */
    public const REGISTRY = [
        'string' => UntitledSingleSelectEnumSchema::class,
    ];

    /**
     * Creates an instance from an array.
     *
     * @param array<string, mixed> $data
     * @return SingleSelectEnumSchemaInterface
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): SingleSelectEnumSchemaInterface
    {
        if (!isset($data['type'])) {
            throw new \InvalidArgumentException('Missing discriminator field: type');
        }

        switch ($data['type']) {
            case 'string':
                if (isset($data['enum'])) {
                    return UntitledSingleSelectEnumSchema::fromArray($data);
                }
                elseif (isset($data['oneOf'])) {
                    return TitledSingleSelectEnumSchema::fromArray($data);
                }
                else {
                    return UntitledSingleSelectEnumSchema::fromArray($data);
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
