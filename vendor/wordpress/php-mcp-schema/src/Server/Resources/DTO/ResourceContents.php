<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Resources\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * The contents of a specific resource or sub-resource.
 *
 * @since 2024-11-05
 * @last-updated 2025-06-18 (added properties: _meta)
 *
 * @mcp-domain Server
 * @mcp-subdomain Resources
 * @mcp-version 2025-11-25
 */
class ResourceContents extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * The URI of this resource.
     *
     * @since 2024-11-05
     *
     * @var string
     */
    protected string $uri;

    /**
     * The MIME type of this resource, if known.
     *
     * @since 2024-11-05
     *
     * @var string|null
     */
    protected ?string $mimeType;

    /**
     * See [General fields: `_meta`](/specification/2025-11-25/basic/index#meta) for notes on `_meta` usage.
     *
     * @since 2025-06-18
     *
     * @var array<string, mixed>|null
     */
    protected ?array $_meta;

    /**
     * @param string $uri @since 2024-11-05
     * @param string|null $mimeType @since 2024-11-05
     * @param array<string, mixed>|null $_meta @since 2025-06-18
     */
    public function __construct(
        string $uri,
        ?string $mimeType = null,
        ?array $_meta = null
    ) {
        $this->uri = $uri;
        $this->mimeType = $mimeType;
        $this->_meta = $_meta;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     uri: string,
     *     mimeType?: string|null,
     *     _meta?: array<string, mixed>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['uri']);

        return new self(
            self::asString($data['uri']),
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
        $result = [];

        $result['uri'] = $this->uri;
        if ($this->mimeType !== null) {
            $result['mimeType'] = $this->mimeType;
        }
        if ($this->_meta !== null) {
            $result['_meta'] = $this->_meta;
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @return string|null
     */
    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_meta(): ?array
    {
        return $this->_meta;
    }
}
