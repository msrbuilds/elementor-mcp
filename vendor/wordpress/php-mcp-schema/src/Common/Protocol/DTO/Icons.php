<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Core\DTO\Icon;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Base interface to add `icons` property.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class Icons extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * Optional set of sized icons that the client can display in a user interface.
     *
     * Clients that support rendering icons MUST support at least the following MIME types:
     * - `image/png` - PNG images (safe, universal compatibility)
     * - `image/jpeg` (and `image/jpg`) - JPEG images (safe, universal compatibility)
     *
     * Clients that support rendering icons SHOULD also support:
     * - `image/svg+xml` - SVG images (scalable but requires security precautions)
     * - `image/webp` - WebP images (modern, efficient format)
     *
     * @since 2025-11-25
     *
     * @var array<\WP\McpSchema\Common\Core\DTO\Icon>|null
     */
    protected ?array $icons;

    /**
     * @param array<\WP\McpSchema\Common\Core\DTO\Icon>|null $icons @since 2025-11-25
     */
    public function __construct(
        ?array $icons = null
    ) {
        $this->icons = $icons;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     icons?: array<array<string, mixed>|\WP\McpSchema\Common\Core\DTO\Icon>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        /** @var array<\WP\McpSchema\Common\Core\DTO\Icon>|null $icons */
        $icons = isset($data['icons'])
            ? array_map(
                static fn($item) => is_array($item)
                    ? Icon::fromArray($item)
                    : $item,
                self::asArray($data['icons'])
            )
            : null;

        return new self(
            $icons
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

        if ($this->icons !== null) {
            $result['icons'] = array_map(static fn($item) => $item->toArray(), $this->icons);
        }

        return $result;
    }

    /**
     * @return array<\WP\McpSchema\Common\Core\DTO\Icon>|null
     */
    public function getIcons(): ?array
    {
        return $this->icons;
    }
}
