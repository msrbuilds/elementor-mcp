<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Common\Traits\ValidatesRequiredFields;
use WP\McpSchema\Server\Resources\DTO\ResourceContents;

/**
 * @since 2024-11-05
 * @last-updated 2025-06-18 (added properties: _meta)
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class BlobResourceContents extends ResourceContents
{
    use ValidatesRequiredFields;

    /**
     * A base64-encoded string representing the binary data of the item.
     *
     * @since 2024-11-05
     *
     * @var string
     */
    protected string $blob;

    /**
     * @param string $uri @since 2024-11-05
     * @param string $blob @since 2024-11-05
     * @param string|null $mimeType @since 2024-11-05
     * @param array<string, mixed>|null $_meta @since 2025-06-18
     */
    public function __construct(
        string $uri,
        string $blob,
        ?string $mimeType = null,
        ?array $_meta = null
    ) {
        parent::__construct($uri, $mimeType, $_meta);
        $this->blob = $blob;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     uri: string,
     *     mimeType?: string|null,
     *     _meta?: array<string, mixed>|null,
     *     blob: string
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['uri', 'blob']);

        return new self(
            self::asString($data['uri']),
            self::asString($data['blob']),
            self::asStringOrNull($data['mimeType'] ?? null),
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

        $result['blob'] = $this->blob;

        return $result;
    }

    /**
     * @return string
     */
    public function getBlob(): string
    {
        return $this->blob;
    }
}
