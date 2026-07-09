<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Resources\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\NotificationParams;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Parameters for a `notifications/resources/updated` notification.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Server
 * @mcp-subdomain Resources
 * @mcp-version 2025-11-25
 */
class ResourceUpdatedNotificationParams extends NotificationParams
{
    use ValidatesRequiredFields;

    /**
     * The URI of the resource that has been updated. This might be a sub-resource of the one that the client actually subscribed to.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $uri;

    /**
     * @param string $uri @since 2025-11-25
     * @param array<string, mixed>|null $_meta @since 2025-11-25
     */
    public function __construct(
        string $uri,
        ?array $_meta = null
    ) {
        parent::__construct($_meta);
        $this->uri = $uri;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|null,
     *     uri: string
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['uri']);

        return new self(
            self::asString($data['uri']),
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

        $result['uri'] = $this->uri;

        return $result;
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }
}
