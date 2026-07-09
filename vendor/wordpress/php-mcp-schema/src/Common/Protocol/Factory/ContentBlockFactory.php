<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\Factory;

use WP\McpSchema\Common\Protocol\Union\ContentBlockInterface;
use WP\McpSchema\Common\Content\DTO\TextContent;
use WP\McpSchema\Common\Content\DTO\ImageContent;
use WP\McpSchema\Common\Content\DTO\AudioContent;
use WP\McpSchema\Server\Resources\DTO\ResourceLink;
use WP\McpSchema\Common\Protocol\DTO\EmbeddedResource;

/**
 * Factory for creating ContentBlock union type instances.
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
final class ContentBlockFactory
{
    /**
     * Registry mapping discriminator values to implementation classes.
     *
     * @var array<string, class-string<ContentBlockInterface>>
     */
    public const REGISTRY = [
        'text' => TextContent::class,
        'image' => ImageContent::class,
        'audio' => AudioContent::class,
        'resource_link' => ResourceLink::class,
        'resource' => EmbeddedResource::class,
    ];

    /**
     * Creates an instance from an array.
     *
     * @param array<string, mixed> $data
     * @return ContentBlockInterface
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): ContentBlockInterface
    {
        if (!isset($data['type'])) {
            throw new \InvalidArgumentException('Missing discriminator field: type');
        }

        /** @var string $type */
        $type = $data['type'];
        if (!isset(self::REGISTRY[$type])) {
            throw new \InvalidArgumentException(sprintf(
                "Unknown type value '%s'. Valid values: %s",
                $type,
                implode(', ', array_keys(self::REGISTRY))
            ));
        }

        $class = self::REGISTRY[$type];
        return $class::fromArray($data);
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
