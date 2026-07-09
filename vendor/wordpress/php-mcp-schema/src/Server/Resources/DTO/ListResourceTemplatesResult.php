<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Resources\DTO;

use WP\McpSchema\Common\Protocol\DTO\PaginatedResult;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;
use WP\McpSchema\Server\Lifecycle\Union\ServerResultInterface;

/**
 * The server's response to a resources/templates/list request from the client.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: resourceTemplates)
 *
 * @mcp-domain Server
 * @mcp-subdomain Resources
 * @mcp-version 2025-11-25
 */
class ListResourceTemplatesResult extends PaginatedResult implements ServerResultInterface
{
    use ValidatesRequiredFields;

    /**
     * @since 2024-11-05
     *
     * @var array<\WP\McpSchema\Server\Resources\DTO\ResourceTemplate>
     */
    protected array $resourceTemplates;

    /**
     * @param array<\WP\McpSchema\Server\Resources\DTO\ResourceTemplate> $resourceTemplates @since 2024-11-05
     * @param string|null $nextCursor @since 2024-11-05
     * @param array<string, mixed>|null $_meta @since 2024-11-05
     */
    public function __construct(
        array $resourceTemplates,
        ?string $nextCursor = null,
        ?array $_meta = null
    ) {
        parent::__construct($_meta, $nextCursor);
        $this->resourceTemplates = $resourceTemplates;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     nextCursor?: string|null,
     *     _meta?: array<string, mixed>|null,
     *     resourceTemplates: array<array<string, mixed>|\WP\McpSchema\Server\Resources\DTO\ResourceTemplate>
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['resourceTemplates']);

        /** @var array<\WP\McpSchema\Server\Resources\DTO\ResourceTemplate> $resourceTemplates */
        $resourceTemplates = array_map(
            static fn($item) => is_array($item)
                ? ResourceTemplate::fromArray($item)
                : $item,
            self::asArray($data['resourceTemplates'])
        );

        return new self(
            $resourceTemplates,
            self::asStringOrNull($data['nextCursor'] ?? null),
            self::asArrayOrNull($data['_meta'] ?? null)
        );
    }

    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = parent::toArray();

        $result['resourceTemplates'] = array_map(static fn($item) => $item->toArray(), $this->resourceTemplates);

        return $result;
    }

    /**
     * @return array<\WP\McpSchema\Server\Resources\DTO\ResourceTemplate>
     */
    public function getResourceTemplates(): array
    {
        return $this->resourceTemplates;
    }
}
