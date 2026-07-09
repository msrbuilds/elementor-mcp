<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Sampling\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Protocol\Factory\SamplingMessageContentBlockFactory;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Describes a message issued to or received from an LLM API.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (added properties: _meta; modified properties: content, role)
 *
 * @mcp-domain Client
 * @mcp-subdomain Sampling
 * @mcp-version 2025-11-25
 */
class SamplingMessage extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * @since 2024-11-05
     *
     * @var 'user'|'assistant'
     */
    protected string $role;

    /**
     * @since 2024-11-05
     *
     * @var array<\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface|\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface>
     */
    protected array $content;

    /**
     * See [General fields: `_meta`](/specification/2025-11-25/basic/index#meta) for notes on `_meta` usage.
     *
     * @since 2025-11-25
     *
     * @var array<string, mixed>|null
     */
    protected ?array $_meta;

    /**
     * @param 'user'|'assistant' $role @since 2024-11-05
     * @param array<\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface|\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface> $content @since 2024-11-05
     * @param array<string, mixed>|null $_meta @since 2025-11-25
     */
    public function __construct(
        string $role,
        array $content,
        ?array $_meta = null
    ) {
        $this->role = $role;
        $this->content = $content;
        $this->_meta = $_meta;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     role: 'user'|'assistant',
     *     content: array<\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface|\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface>,
     *     _meta?: array<string, mixed>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['role', 'content']);

        /** @var 'user'|'assistant' $role */
        $role = self::asString($data['role']);

        /** @var array<\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface|\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface> $content */
        $content = array_map(
            static fn($item) => is_array($item)
                ? SamplingMessageContentBlockFactory::fromArray($item)
                : $item,
            self::asArray($data['content'])
        );

        return new self(
            $role,
            $content,
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

        $result['role'] = $this->role;
        $result['content'] = array_map(static fn($item) => (is_object($item) && method_exists($item, 'toArray')) ? $item->toArray() : $item, $this->content);
        if ($this->_meta !== null) {
            $result['_meta'] = $this->_meta;
        }

        return $result;
    }

    /**
     * @return 'user'|'assistant'
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * @return array<\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface|\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface>
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_meta(): ?array
    {
        return $this->_meta;
    }
}
