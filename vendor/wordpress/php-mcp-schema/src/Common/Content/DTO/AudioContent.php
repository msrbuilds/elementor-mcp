<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Content\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Protocol\DTO\Annotations;
use WP\McpSchema\Common\Protocol\Union\ContentBlockInterface;
use WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Audio provided to or from an LLM.
 *
 * @since 2025-03-26
 * @last-updated 2025-11-25 (modified property: annotations)
 *
 * @mcp-domain Common
 * @mcp-subdomain Content
 * @mcp-version 2025-11-25
 */
class AudioContent extends AbstractDataTransferObject implements SamplingMessageContentBlockInterface, ContentBlockInterface
{
    use ValidatesRequiredFields;

    public const TYPE = 'audio';

    public const DISCRIMINATOR_FIELD = 'type';
    public const DISCRIMINATOR_VALUE = 'audio';

    /**
     * @since 2025-03-26
     *
     * @var 'audio'
     */
    protected string $type;

    /**
     * The base64-encoded audio data.
     *
     * @since 2025-03-26
     *
     * @var string
     */
    protected string $data;

    /**
     * The MIME type of the audio. Different providers may support different audio types.
     *
     * @since 2025-03-26
     *
     * @var string
     */
    protected string $mimeType;

    /**
     * Optional annotations for the client.
     *
     * @since 2025-03-26
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
     * @param string $data @since 2025-03-26
     * @param string $mimeType @since 2025-03-26
     * @param \WP\McpSchema\Common\Protocol\DTO\Annotations|null $annotations @since 2025-03-26
     * @param array<string, mixed>|null $_meta @since 2025-06-18
     */
    public function __construct(
        string $data,
        string $mimeType,
        ?Annotations $annotations = null,
        ?array $_meta = null
    ) {
        $this->type = self::TYPE;
        $this->data = $data;
        $this->mimeType = $mimeType;
        $this->annotations = $annotations;
        $this->_meta = $_meta;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     type: 'audio',
     *     data: string,
     *     mimeType: string,
     *     annotations?: array<string, mixed>|\WP\McpSchema\Common\Protocol\DTO\Annotations|null,
     *     _meta?: array<string, mixed>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['data', 'mimeType']);

        /** @var \WP\McpSchema\Common\Protocol\DTO\Annotations|null $annotations */
        $annotations = isset($data['annotations'])
            ? (is_array($data['annotations'])
                ? Annotations::fromArray(self::asArray($data['annotations']))
                : $data['annotations'])
            : null;

        return new self(
            self::asString($data['data']),
            self::asString($data['mimeType']),
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
        $result['data'] = $this->data;
        $result['mimeType'] = $this->mimeType;
        if ($this->annotations !== null) {
            $result['annotations'] = $this->annotations->toArray();
        }
        if ($this->_meta !== null) {
            $result['_meta'] = $this->_meta;
        }

        return $result;
    }

    /**
     * @return 'audio'
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function getMimeType(): string
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
     * @return array<string, mixed>|null
     */
    public function get_meta(): ?array
    {
        return $this->_meta;
    }
}
