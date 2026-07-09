<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Optional annotations for the client. The client can use annotations to inform how objects are used or displayed
 *
 * @since 2025-03-26
 * @last-updated 2025-11-25 (modified property: audience)
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class Annotations extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * Describes who the intended audience of this object or data is.
     *
     * It can include multiple entries to indicate content useful for multiple audiences (e.g., `["user", "assistant"]`).
     *
     * @since 2025-03-26
     *
     * @var array<'user'|'assistant'>|null
     */
    protected ?array $audience;

    /**
     * Describes how important this data is for operating the server.
     *
     * A value of 1 means "most important," and indicates that the data is
     * effectively required, while 0 means "least important," and indicates that
     * the data is entirely optional.
     *
     * @since 2025-03-26
     *
     * @var float|null
     */
    protected ?float $priority;

    /**
     * The moment the resource was last modified, as an ISO 8601 formatted string.
     *
     * Should be an ISO 8601 formatted string (e.g., "2025-01-12T15:00:58Z").
     *
     * Examples: last activity timestamp in an open file, timestamp when the resource
     * was attached, etc.
     *
     * @since 2025-06-18
     *
     * @var string|null
     */
    protected ?string $lastModified;

    /**
     * @param array<'user'|'assistant'>|null $audience @since 2025-03-26
     * @param float|null $priority @since 2025-03-26
     * @param string|null $lastModified @since 2025-06-18
     */
    public function __construct(
        ?array $audience = null,
        ?float $priority = null,
        ?string $lastModified = null
    ) {
        $this->audience = $audience;
        $this->priority = $priority;
        $this->lastModified = $lastModified;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     audience?: array<'user'|'assistant'>|null,
     *     priority?: float|null,
     *     lastModified?: string|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        /** @var array<'user'|'assistant'>|null $audience */
        $audience = isset($data['audience'])
            ? self::asStringArrayOrNull($data['audience'])
            : null;

        return new self(
            $audience,
            self::asFloatOrNull($data['priority'] ?? null),
            self::asStringOrNull($data['lastModified'] ?? null)
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

        if ($this->audience !== null) {
            $result['audience'] = $this->audience;
        }
        if ($this->priority !== null) {
            $result['priority'] = $this->priority;
        }
        if ($this->lastModified !== null) {
            $result['lastModified'] = $this->lastModified;
        }

        return $result;
    }

    /**
     * @return array<'user'|'assistant'>|null
     */
    public function getAudience(): ?array
    {
        return $this->audience;
    }

    /**
     * @return float|null
     */
    public function getPriority(): ?float
    {
        return $this->priority;
    }

    /**
     * @return string|null
     */
    public function getLastModified(): ?string
    {
        return $this->lastModified;
    }
}
