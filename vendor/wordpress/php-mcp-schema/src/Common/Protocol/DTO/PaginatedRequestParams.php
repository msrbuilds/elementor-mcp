<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\RequestParams;
use WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Common parameters for paginated requests.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class PaginatedRequestParams extends RequestParams
{
    use ValidatesRequiredFields;

    /**
     * An opaque token representing the current pagination position.
     * If provided, the server should return results starting after this cursor.
     *
     * @since 2025-11-25
     *
     * @var string|null
     */
    protected ?string $cursor;

    /**
     * @param \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta @since 2025-11-25
     * @param string|null $cursor @since 2025-11-25
     */
    public function __construct(
        ?RequestParamsMeta $_meta = null,
        ?string $cursor = null
    ) {
        parent::__construct($_meta);
        $this->cursor = $cursor;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|\WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null,
     *     cursor?: string|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        /** @var \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta */
        $_meta = isset($data['_meta'])
            ? (is_array($data['_meta'])
                ? RequestParamsMeta::fromArray(self::asArray($data['_meta']))
                : $data['_meta'])
            : null;

        return new self(
            $_meta,
            self::asStringOrNull($data['cursor'] ?? null)
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

        if ($this->cursor !== null) {
            $result['cursor'] = $this->cursor;
        }

        return $result;
    }

    /**
     * @return string|null
     */
    public function getCursor(): ?string
    {
        return $this->cursor;
    }
}
