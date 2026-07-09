<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Resources\DTO;

use WP\McpSchema\Common\Protocol\DTO\Result;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;
use WP\McpSchema\Server\Lifecycle\Union\ServerResultInterface;

/**
 * The server's response to a resources/read request from the client.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: contents)
 *
 * @mcp-domain Server
 * @mcp-subdomain Resources
 * @mcp-version 2025-11-25
 */
class ReadResourceResult extends Result implements ServerResultInterface
{
    use ValidatesRequiredFields;

    /**
     * @since 2024-11-05
     *
     * @var array<\WP\McpSchema\Common\Protocol\DTO\TextResourceContents|\WP\McpSchema\Common\Protocol\DTO\BlobResourceContents>
     */
    protected array $contents;

    /**
     * @param array<\WP\McpSchema\Common\Protocol\DTO\TextResourceContents|\WP\McpSchema\Common\Protocol\DTO\BlobResourceContents> $contents @since 2024-11-05
     * @param array<string, mixed>|null $_meta @since 2024-11-05
     */
    public function __construct(
        array $contents,
        ?array $_meta = null
    ) {
        parent::__construct($_meta);
        $this->contents = $contents;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|null,
     *     contents: array<\WP\McpSchema\Common\Protocol\DTO\TextResourceContents|\WP\McpSchema\Common\Protocol\DTO\BlobResourceContents>
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['contents']);

        /** @var array<\WP\McpSchema\Common\Protocol\DTO\TextResourceContents|\WP\McpSchema\Common\Protocol\DTO\BlobResourceContents> $contents */
        $contents = self::asArray($data['contents']);

        return new self(
            $contents,
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

        $result['contents'] = array_map(static fn($item) => (is_object($item) && method_exists($item, 'toArray')) ? $item->toArray() : $item, $this->contents);

        return $result;
    }

    /**
     * @return array<\WP\McpSchema\Common\Protocol\DTO\TextResourceContents|\WP\McpSchema\Common\Protocol\DTO\BlobResourceContents>
     */
    public function getContents(): array
    {
        return $this->contents;
    }
}
