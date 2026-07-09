<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Lifecycle\DTO;

use WP\McpSchema\Common\Core\DTO\Icon;
use WP\McpSchema\Common\Protocol\DTO\BaseMetadata;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Describes the MCP implementation.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (added properties: description, icons, websiteUrl)
 *
 * @mcp-domain Common
 * @mcp-subdomain Lifecycle
 * @mcp-version 2025-11-25
 */
class Implementation extends BaseMetadata
{
    use ValidatesRequiredFields;

    /**
     * @since 2024-11-05
     *
     * @var string
     */
    protected string $version;

    /**
     * An optional human-readable description of what this implementation does.
     *
     * This can be used by clients or servers to provide context about their purpose
     * and capabilities. For example, a server might describe the types of resources
     * or tools it provides, while a client might describe its intended use case.
     *
     * @since 2025-11-25
     *
     * @var string|null
     */
    protected ?string $description;

    /**
     * An optional URL of the website for this implementation.
     *
     * @since 2025-11-25
     *
     * @var string|null
     */
    protected ?string $websiteUrl;

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
     * @param string $name @since 2024-11-05
     * @param string $version @since 2024-11-05
     * @param string|null $title @since 2025-06-18
     * @param string|null $description @since 2025-11-25
     * @param string|null $websiteUrl @since 2025-11-25
     * @param array<\WP\McpSchema\Common\Core\DTO\Icon>|null $icons @since 2025-11-25
     */
    public function __construct(
        string $name,
        string $version,
        ?string $title = null,
        ?string $description = null,
        ?string $websiteUrl = null,
        ?array $icons = null
    ) {
        parent::__construct($name, $title);
        $this->version = $version;
        $this->description = $description;
        $this->websiteUrl = $websiteUrl;
        $this->icons = $icons;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     name: string,
     *     title?: string|null,
     *     version: string,
     *     description?: string|null,
     *     websiteUrl?: string|null,
     *     icons?: array<array<string, mixed>|\WP\McpSchema\Common\Core\DTO\Icon>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['name', 'version']);

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
            self::asString($data['name']),
            self::asString($data['version']),
            self::asStringOrNull($data['title'] ?? null),
            self::asStringOrNull($data['description'] ?? null),
            self::asStringOrNull($data['websiteUrl'] ?? null),
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
        $result = parent::toArray();

        $result['version'] = $this->version;
        if ($this->description !== null) {
            $result['description'] = $this->description;
        }
        if ($this->websiteUrl !== null) {
            $result['websiteUrl'] = $this->websiteUrl;
        }
        if ($this->icons !== null) {
            $result['icons'] = array_map(static fn($item) => $item->toArray(), $this->icons);
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return string|null
     */
    public function getWebsiteUrl(): ?string
    {
        return $this->websiteUrl;
    }

    /**
     * @return array<\WP\McpSchema\Common\Core\DTO\Icon>|null
     */
    public function getIcons(): ?array
    {
        return $this->icons;
    }
}
