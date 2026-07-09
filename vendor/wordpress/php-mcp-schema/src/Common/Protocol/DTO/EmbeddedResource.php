<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Protocol\Union\ContentBlockInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * The contents of a resource, embedded into a prompt or tool call result.
 *
 * It is up to the client how best to render embedded resources for the benefit
 * of the LLM and/or the user.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: annotations)
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class EmbeddedResource extends AbstractDataTransferObject implements ContentBlockInterface
{
    use ValidatesRequiredFields;

    public const TYPE = 'resource';

    public const DISCRIMINATOR_FIELD = 'type';
    public const DISCRIMINATOR_VALUE = 'resource';

    /**
     * @since 2024-11-05
     *
     * @var 'resource'
     */
    protected string $type;

    /**
     * @since 2024-11-05
     *
     * @var \WP\McpSchema\Common\Protocol\DTO\TextResourceContents|\WP\McpSchema\Common\Protocol\DTO\BlobResourceContents
     */
    protected $resource;

    /**
     * Optional annotations for the client.
     *
     * @since 2024-11-05
     *
     * @var \WP\McpSchema\Common\Protocol\DTO\Annotations|null
     */
    protected ?Annotations $annotations;

    /**
     * See [General fields: `_meta`](/specification/2025-11-25/basic/index#meta) for notes on `_meta` usage.
     *
     * @since 2025-06-18
     *
     * @var array<string, mixed>|null
     */
    protected ?array $_meta;

    /**
     * @param \WP\McpSchema\Common\Protocol\DTO\TextResourceContents|\WP\McpSchema\Common\Protocol\DTO\BlobResourceContents $resource @since 2024-11-05
     * @param \WP\McpSchema\Common\Protocol\DTO\Annotations|null $annotations @since 2024-11-05
     * @param array<string, mixed>|null $_meta @since 2025-06-18
     */
    public function __construct(
        $resource,
        ?Annotations $annotations = null,
        ?array $_meta = null
    ) {
        $this->type = self::TYPE;
        $this->resource = $resource;
        $this->annotations = $annotations;
        $this->_meta = $_meta;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     type: 'resource',
     *     resource: \WP\McpSchema\Common\Protocol\DTO\TextResourceContents|\WP\McpSchema\Common\Protocol\DTO\BlobResourceContents,
     *     annotations?: array<string, mixed>|\WP\McpSchema\Common\Protocol\DTO\Annotations|null,
     *     _meta?: array<string, mixed>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['resource']);

        /** @var \WP\McpSchema\Common\Protocol\DTO\TextResourceContents|\WP\McpSchema\Common\Protocol\DTO\BlobResourceContents $resource */
        $resource = $data['resource'];

        /** @var \WP\McpSchema\Common\Protocol\DTO\Annotations|null $annotations */
        $annotations = isset($data['annotations'])
            ? (is_array($data['annotations'])
                ? Annotations::fromArray(self::asArray($data['annotations']))
                : $data['annotations'])
            : null;

        return new self(
            $resource,
            $annotations,
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
        $result = [];

        $result['type'] = $this->type;
        $result['resource'] = (is_object($this->resource) && method_exists($this->resource, 'toArray')) ? $this->resource->toArray() : $this->resource;
        if ($this->annotations !== null) {
            $result['annotations'] = $this->annotations->toArray();
        }
        if ($this->_meta !== null) {
            $result['_meta'] = $this->_meta;
        }

        return $result;
    }

    /**
     * @return 'resource'
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return \WP\McpSchema\Common\Protocol\DTO\TextResourceContents|\WP\McpSchema\Common\Protocol\DTO\BlobResourceContents
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @return \WP\McpSchema\Common\Protocol\DTO\Annotations|null
     */
    public function getAnnotations(): ?Annotations
    {
        return $this->annotations;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_meta(): ?array
    {
        return $this->_meta;
    }
}
