<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * @since 2024-11-05
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class PaginatedResult extends Result
{
    use ValidatesRequiredFields;

    /**
     * An opaque token representing the pagination position after the last returned result.
     * If present, there may be more results available.
     *
     * @since 2024-11-05
     *
     * @var string|null
     */
    protected ?string $nextCursor;

    /**
     * @param array<string, mixed>|null $_meta @since 2024-11-05
     * @param string|null $nextCursor @since 2024-11-05
     */
    public function __construct(
        ?array $_meta = null,
        ?string $nextCursor = null
    ) {
        parent::__construct($_meta);
        $this->nextCursor = $nextCursor;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|null,
     *     nextCursor?: string|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::asArrayOrNull($data['_meta'] ?? null),
            self::asStringOrNull($data['nextCursor'] ?? null)
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

        if ($this->nextCursor !== null) {
            $result['nextCursor'] = $this->nextCursor;
        }

        return $result;
    }

    /**
     * @return string|null
     */
    public function getNextCursor(): ?string
    {
        return $this->nextCursor;
    }
}
