<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Resources\DTO;

use WP\McpSchema\Common\Core\DTO\Icon;
use WP\McpSchema\Common\Protocol\DTO\Annotations;
use WP\McpSchema\Common\Protocol\Union\ContentBlockInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * A resource that the server is capable of reading, included in a prompt or tool call result.
 *
 * Note: resource links returned by tools are not guaranteed to appear in the results of `resources/list` requests.
 *
 * @since 2025-06-18
 * @last-updated 2025-11-25 (added properties: icons; modified property: annotations)
 *
 * @mcp-domain Server
 * @mcp-subdomain Resources
 * @mcp-version 2025-11-25
 */
class ResourceLink extends Resource implements ContentBlockInterface
{
    use ValidatesRequiredFields;

    public const TYPE = 'resource_link';

    public const DISCRIMINATOR_FIELD = 'type';
    public const DISCRIMINATOR_VALUE = 'resource_link';

    /**
     * @since 2025-06-18
     *
     * @var 'resource_link'
     */
    protected string $type;

    /**
     * @param string $uri @since 2025-06-18
     * @param string $name @since 2025-06-18
     * @param string|null $description @since 2025-06-18
     * @param string|null $mimeType @since 2025-06-18
     * @param \WP\McpSchema\Common\Protocol\DTO\Annotations|null $annotations @since 2025-06-18
     * @param int|null $size @since 2025-06-18
     * @param array<string, mixed>|null $_meta @since 2025-06-18
     * @param string|null $title @since 2025-06-18
     * @param array<\WP\McpSchema\Common\Core\DTO\Icon>|null $icons @since 2025-11-25
     */
    public function __construct(
        string $uri,
        string $name,
        ?string $description = null,
        ?string $mimeType = null,
        ?Annotations $annotations = null,
        ?int $size = null,
        ?array $_meta = null,
        ?string $title = null,
        ?array $icons = null
    ) {
        parent::__construct($name, $uri, $title, $description, $mimeType, $annotations, $size, $_meta, $icons);
        $this->type = self::TYPE;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     uri: string,
     *     description?: string|null,
     *     mimeType?: string|null,
     *     annotations?: array<string, mixed>|\WP\McpSchema\Common\Protocol\DTO\Annotations|null,
     *     size?: int|null,
     *     _meta?: array<string, mixed>|null,
     *     name: string,
     *     title?: string|null,
     *     icons?: array<array<string, mixed>|\WP\McpSchema\Common\Core\DTO\Icon>|null,
     *     type: 'resource_link'
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['uri', 'name']);

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
            self::asString($data['uri']),
            self::asString($data['name']),
            self::asStringOrNull($data['description'] ?? null),
            self::asStringOrNull($data['mimeType'] ?? null),
            $annotations,
            self::asIntOrNull($data['size'] ?? null),
            self::asArrayOrNull($data['_meta'] ?? null),
            self::asStringOrNull($data['title'] ?? null),
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

        $result['type'] = $this->type;

        return $result;
    }

    /**
     * @return 'resource_link'
     */
    public function getType(): string
    {
        return $this->type;
    }
}
