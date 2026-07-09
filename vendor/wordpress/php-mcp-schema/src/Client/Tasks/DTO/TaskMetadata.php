<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Tasks\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Metadata for augmenting a request with task execution.
 * Include this in the `task` field of the request parameters.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Client
 * @mcp-subdomain Tasks
 * @mcp-version 2025-11-25
 */
class TaskMetadata extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * Requested duration in milliseconds to retain task from creation.
     *
     * @since 2025-11-25
     *
     * @var int|null
     */
    protected ?int $ttl;

    /**
     * @param int|null $ttl @since 2025-11-25
     */
    public function __construct(
        ?int $ttl = null
    ) {
        $this->ttl = $ttl;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     ttl?: int|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::asIntOrNull($data['ttl'] ?? null)
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

        if ($this->ttl !== null) {
            $result['ttl'] = $this->ttl;
        }

        return $result;
    }

    /**
     * @return int|null
     */
    public function getTtl(): ?int
    {
        return $this->ttl;
    }
}
