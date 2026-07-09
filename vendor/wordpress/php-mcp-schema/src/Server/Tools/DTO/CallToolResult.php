<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Tools\DTO;

use WP\McpSchema\Common\Protocol\DTO\Result;
use WP\McpSchema\Common\Protocol\Factory\ContentBlockFactory;
use WP\McpSchema\Common\Protocol\Union\ContentBlockInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;
use WP\McpSchema\Server\Lifecycle\Union\ServerResultInterface;

/**
 * The server's response to a tool call.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: content)
 *
 * @mcp-domain Server
 * @mcp-subdomain Tools
 * @mcp-version 2025-11-25
 */
class CallToolResult extends Result implements ServerResultInterface
{
    use ValidatesRequiredFields;

    /**
     * A list of content objects that represent the unstructured result of the tool call.
     *
     * @since 2024-11-05
     *
     * @var array<\WP\McpSchema\Common\Protocol\Union\ContentBlockInterface>
     */
    protected array $content;

    /**
     * An optional JSON object that represents the structured result of the tool call.
     *
     * @since 2025-06-18
     *
     * @var array<string, mixed>|null
     */
    protected ?array $structuredContent;

    /**
     * Whether the tool call ended in an error.
     *
     * If not set, this is assumed to be false (the call was successful).
     *
     * Any errors that originate from the tool SHOULD be reported inside the result
     * object, with `isError` set to true, _not_ as an MCP protocol-level error
     * response. Otherwise, the LLM would not be able to see that an error occurred
     * and self-correct.
     *
     * However, any errors in _finding_ the tool, an error indicating that the
     * server does not support tool calls, or any other exceptional conditions,
     * should be reported as an MCP error response.
     *
     * @since 2024-11-05
     *
     * @var bool|null
     */
    protected ?bool $isError;

    /**
     * @param array<\WP\McpSchema\Common\Protocol\Union\ContentBlockInterface> $content @since 2024-11-05
     * @param array<string, mixed>|null $_meta @since 2024-11-05
     * @param array<string, mixed>|null $structuredContent @since 2025-06-18
     * @param bool|null $isError @since 2024-11-05
     */
    public function __construct(
        array $content,
        ?array $_meta = null,
        ?array $structuredContent = null,
        ?bool $isError = null
    ) {
        parent::__construct($_meta);
        $this->content = $content;
        $this->structuredContent = $structuredContent;
        $this->isError = $isError;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|null,
     *     content: array<array<string, mixed>|\WP\McpSchema\Common\Protocol\Union\ContentBlockInterface>,
     *     structuredContent?: array<string, mixed>|null,
     *     isError?: bool|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['content']);

        /** @var array<\WP\McpSchema\Common\Protocol\Union\ContentBlockInterface> $content */
        $content = array_map(
            static fn($item) => is_array($item)
                ? ContentBlockFactory::fromArray($item)
                : $item,
            self::asArray($data['content'])
        );

        return new self(
            $content,
            self::asArrayOrNull($data['_meta'] ?? null),
            self::asArrayOrNull($data['structuredContent'] ?? null),
            self::asBoolOrNull($data['isError'] ?? null)
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

        $result['content'] = array_map(static fn($item) => $item->toArray(), $this->content);
        if ($this->structuredContent !== null) {
            $result['structuredContent'] = $this->structuredContent;
        }
        if ($this->isError !== null) {
            $result['isError'] = $this->isError;
        }

        return $result;
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
}
