<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Base interface for metadata with name (identifier) and title (display name) properties.
 *
 * @since 2025-06-18
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class BaseMetadata extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * Intended for programmatic or logical use, but used as a display name in past specs or fallback (if title isn't present).
     *
     * @since 2025-06-18
     *
     * @var string
     */
    protected string $name;

    /**
     * Intended for UI and end-user contexts — optimized to be human-readable and easily understood,
     * even by those unfamiliar with domain-specific terminology.
     *
     * If not provided, the name should be used for display (except for Tool,
     * where `annotations.title` should be given precedence over using `name`,
     * if present).
     *
     * @since 2025-06-18
     *
     * @var string|null
     */
    protected ?string $title;

    /**
     * @param string $name @since 2025-06-18
     * @param string|null $title @since 2025-06-18
     */
    public function __construct(
        string $name,
        ?string $title = null
    ) {
        $this->name = $name;
        $this->title = $title;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     name: string,
     *     title?: string|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['name']);

        return new self(
            self::asString($data['name']),
            self::asStringOrNull($data['title'] ?? null)
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

        $result['name'] = $this->name;
        if ($this->title !== null) {
            $result['title'] = $this->title;
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }
}
