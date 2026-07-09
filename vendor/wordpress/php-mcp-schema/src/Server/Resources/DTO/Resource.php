<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Resources\DTO;

use WP\McpSchema\Common\Core\DTO\Icon;
use WP\McpSchema\Common\Protocol\DTO\Annotations;
use WP\McpSchema\Common\Protocol\DTO\BaseMetadata;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * A known resource that the server is capable of reading.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (added properties: icons; modified property: annotations)
 *
 * @mcp-domain Server
 * @mcp-subdomain Resources
 * @mcp-version 2025-11-25
 */
class Resource extends BaseMetadata
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
     * A description of what this resource represents.
     *
     * This can be used by clients to improve the LLM's understanding of available resources. It can be thought of like a "hint" to the model.
     *
     * @since 2024-11-05
     *
     * @var string|null
     */
    protected ?string $description;

    /**
     * The MIME type of this resource, if known.
     *
     * @since 2024-11-05
     *
     * @var string|null
     */
    protected ?string $mimeType;

    /**
     * Optional annotations for the client.
     *
     * @since 2024-11-05
     *
     * @var \WP\McpSchema\Common\Protocol\DTO\Annotations|null
     */
    protected ?Annotations $annotations;

    /**
     * The size of the raw resource content, in bytes (i.e., before base64 encoding or any tokenization), if known.
     *
     * This can be used by Hosts to display file sizes and estimate context window usage.
     *
     * @since 2024-11-05
     *
     * @var int|null
     */
    protected ?int $size;

    /**
     * See [General fields: `_meta`](/specification/2025-11-25/basic/index#meta) for notes on `_meta` usage.
     *
     * @since 2025-06-18
     *
     * @var array<string, mixed>|null
     */
    protected ?array $_meta;

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
     * @param string $uri @since 2024-11-05
     * @param string|null $title @since 2025-06-18
     * @param string|null $description @since 2024-11-05
     * @param string|null $mimeType @since 2024-11-05
     * @param \WP\McpSchema\Common\Protocol\DTO\Annotations|null $annotations @since 2024-11-05
     * @param int|null $size @since 2024-11-05
     * @param array<string, mixed>|null $_meta @since 2025-06-18
     * @param array<\WP\McpSchema\Common\Core\DTO\Icon>|null $icons @since 2025-11-25
     */
    public function __construct(
        string $name,
        string $uri,
        ?string $title = null,
        ?string $description = null,
        ?string $mimeType = null,
        ?Annotations $annotations = null,
        ?int $size = null,
        ?array $_meta = null,
        ?array $icons = null
    ) {
        parent::__construct($name, $title);
        $this->uri = $uri;
        $this->description = $description;
        $this->mimeType = $mimeType;
        $this->annotations = $annotations;
        $this->size = $size;
        $this->_meta = $_meta;
        $this->icons = $icons;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     name: string,
     *     title?: string|null,
     *     uri: string,
     *     description?: string|null,
     *     mimeType?: string|null,
     *     annotations?: array<string, mixed>|\WP\McpSchema\Common\Protocol\DTO\Annotations|null,
     *     size?: int|null,
     *     _meta?: array<string, mixed>|null,
     *     icons?: array<array<string, mixed>|\WP\McpSchema\Common\Core\DTO\Icon>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['name', 'uri']);

        /** @var \WP\McpSchema\Common\Protocol\DTO\Annotations|null $annotations */
        $annotations = isset($data['annotations'])
            ? (is_array($data['annotations'])
                ? Annotations::fromArray(self::asArray($data['annotations']))
                : $data['annotations'])
            : null;

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
            self::asString($data['uri']),
            self::asStringOrNull($data['title'] ?? null),
            self::asStringOrNull($data['description'] ?? null),
            self::asStringOrNull($data['mimeType'] ?? null),
            $annotations,
            self::asIntOrNull($data['size'] ?? null),
            self::asArrayOrNull($data['_meta'] ?? null),
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

        $result['uri'] = $this->uri;
        if ($this->description !== null) {
            $result['description'] = $this->description;
        }
        if ($this->mimeType !== null) {
            $result['mimeType'] = $this->mimeType;
        }
        if ($this->annotations !== null) {
            $result['annotations'] = $this->annotations->toArray();
        }
        if ($this->size !== null) {
            $result['size'] = $this->size;
        }
        if ($this->_meta !== null) {
            $result['_meta'] = $this->_meta;
        }
        if ($this->icons !== null) {
            $result['icons'] = array_map(static fn($item) => $item->toArray(), $this->icons);
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
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return string|null
     */
    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * @return \WP\McpSchema\Common\Protocol\DTO\Annotations|null
     */
    public function getAnnotations(): ?Annotations
    {
        return $this->annotations;
    }

    /**
     * @return int|null
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_meta(): ?array
    {
        return $this->_meta;
    }

    /**
     * @return array<\WP\McpSchema\Common\Core\DTO\Icon>|null
     */
    public function getIcons(): ?array
    {
        return $this->icons;
    }
}
