<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Sampling\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Protocol\Factory\ContentBlockFactory;
use WP\McpSchema\Common\Protocol\Union\ContentBlockInterface;
use WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * The result of a tool use, provided by the user back to the assistant.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Client
 * @mcp-subdomain Sampling
 * @mcp-version 2025-11-25
 */
class ToolResultContent extends AbstractDataTransferObject implements SamplingMessageContentBlockInterface
{
    use ValidatesRequiredFields;

    public const TYPE = 'tool_result';

    public const DISCRIMINATOR_FIELD = 'type';
    public const DISCRIMINATOR_VALUE = 'tool_result';

    /**
     * @since 2025-11-25
     *
     * @var 'tool_result'
     */
    protected string $type;

    /**
     * The ID of the tool use this result corresponds to.
     *
     * This MUST match the ID from a previous ToolUseContent.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $toolUseId;

    /**
     * The unstructured result content of the tool use.
     *
     * This has the same format as CallToolResult.content and can include text, images,
     * audio, resource links, and embedded resources.
     *
     * @since 2025-11-25
     *
     * @var array<\WP\McpSchema\Common\Protocol\Union\ContentBlockInterface>
     */
    protected array $content;

    /**
     * An optional structured result object.
     *
     * If the tool defined an outputSchema, this SHOULD conform to that schema.
     *
     * @since 2025-11-25
     *
     * @var array<string, mixed>|null
     */
    protected ?array $structuredContent;

    /**
     * Whether the tool use resulted in an error.
     *
     * If true, the content typically describes the error that occurred.
     * Default: false
     *
     * @since 2025-11-25
     *
     * @var bool|null
     */
    protected ?bool $isError;

    /**
     * Optional metadata about the tool result. Clients SHOULD preserve this field when
     * including tool results in subsequent sampling requests to enable caching optimizations.
     *
     * See [General fields: `_meta`](/specification/2025-11-25/basic/index#meta) for notes on `_meta` usage.
     *
     * @since 2025-11-25
     *
     * @var array<string, mixed>|null
     */
    protected ?array $_meta;

    /**
     * @param string $toolUseId @since 2025-11-25
     * @param array<\WP\McpSchema\Common\Protocol\Union\ContentBlockInterface> $content @since 2025-11-25
     * @param array<string, mixed>|null $structuredContent @since 2025-11-25
     * @param bool|null $isError @since 2025-11-25
     * @param array<string, mixed>|null $_meta @since 2025-11-25
     */
    public function __construct(
        string $toolUseId,
        array $content,
        ?array $structuredContent = null,
        ?bool $isError = null,
        ?array $_meta = null
    ) {
        $this->type = self::TYPE;
        $this->toolUseId = $toolUseId;
        $this->content = $content;
        $this->structuredContent = $structuredContent;
        $this->isError = $isError;
        $this->_meta = $_meta;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     type: 'tool_result',
     *     toolUseId: string,
     *     content: array<array<string, mixed>|\WP\McpSchema\Common\Protocol\Union\ContentBlockInterface>,
     *     structuredContent?: array<string, mixed>|null,
     *     isError?: bool|null,
     *     _meta?: array<string, mixed>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['toolUseId', 'content']);

        /** @var array<\WP\McpSchema\Common\Protocol\Union\ContentBlockInterface> $content */
        $content = array_map(
            static fn($item) => is_array($item)
                ? ContentBlockFactory::fromArray($item)
                : $item,
            self::asArray($data['content'])
        );

        return new self(
            self::asString($data['toolUseId']),
            $content,
            self::asArrayOrNull($data['structuredContent'] ?? null),
            self::asBoolOrNull($data['isError'] ?? null),
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
        $result['toolUseId'] = $this->toolUseId;
        $result['content'] = array_map(static fn($item) => $item->toArray(), $this->content);
        if ($this->structuredContent !== null) {
            $result['structuredContent'] = $this->structuredContent;
        }
        if ($this->isError !== null) {
            $result['isError'] = $this->isError;
        }
        if ($this->_meta !== null) {
            $result['_meta'] = $this->_meta;
        }

        return $result;
    }

    /**
     * @return 'tool_result'
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getToolUseId(): string
    {
        return $this->toolUseId;
    }

    /**
     * @return array<\WP\McpSchema\Common\Protocol\Union\ContentBlockInterface>
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStructuredContent(): ?array
    {
        return $this->structuredContent;
    }

    /**
     * @return bool|null
     */
    public function getIsError(): ?bool
    {
        return $this->isError;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_meta(): ?array
    {
        return $this->_meta;
    }
}
