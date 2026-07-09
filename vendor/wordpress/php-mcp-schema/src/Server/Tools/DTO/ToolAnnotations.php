<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Tools\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Additional properties describing a Tool to clients.
 *
 * NOTE: all properties in ToolAnnotations are **hints**.
 * They are not guaranteed to provide a faithful description of
 * tool behavior (including descriptive properties like `title`).
 *
 * Clients should never make tool use decisions based on ToolAnnotations
 * received from untrusted servers.
 *
 * @since 2025-03-26
 *
 * @mcp-domain Server
 * @mcp-subdomain Tools
 * @mcp-version 2025-11-25
 */
class ToolAnnotations extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * A human-readable title for the tool.
     *
     * @since 2025-03-26
     *
     * @var string|null
     */
    protected ?string $title;

    /**
     * If true, the tool does not modify its environment.
     *
     * Default: false
     *
     * @since 2025-03-26
     *
     * @var bool|null
     */
    protected ?bool $readOnlyHint;

    /**
     * If true, the tool may perform destructive updates to its environment.
     * If false, the tool performs only additive updates.
     *
     * (This property is meaningful only when `readOnlyHint == false`)
     *
     * Default: true
     *
     * @since 2025-03-26
     *
     * @var bool|null
     */
    protected ?bool $destructiveHint;

    /**
     * If true, calling the tool repeatedly with the same arguments
     * will have no additional effect on its environment.
     *
     * (This property is meaningful only when `readOnlyHint == false`)
     *
     * Default: false
     *
     * @since 2025-03-26
     *
     * @var bool|null
     */
    protected ?bool $idempotentHint;

    /**
     * If true, this tool may interact with an "open world" of external
     * entities. If false, the tool's domain of interaction is closed.
     * For example, the world of a web search tool is open, whereas that
     * of a memory tool is not.
     *
     * Default: true
     *
     * @since 2025-03-26
     *
     * @var bool|null
     */
    protected ?bool $openWorldHint;

    /**
     * @param string|null $title @since 2025-03-26
     * @param bool|null $readOnlyHint @since 2025-03-26
     * @param bool|null $destructiveHint @since 2025-03-26
     * @param bool|null $idempotentHint @since 2025-03-26
     * @param bool|null $openWorldHint @since 2025-03-26
     */
    public function __construct(
        ?string $title = null,
        ?bool $readOnlyHint = null,
        ?bool $destructiveHint = null,
        ?bool $idempotentHint = null,
        ?bool $openWorldHint = null
    ) {
        $this->title = $title;
        $this->readOnlyHint = $readOnlyHint;
        $this->destructiveHint = $destructiveHint;
        $this->idempotentHint = $idempotentHint;
        $this->openWorldHint = $openWorldHint;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     title?: string|null,
     *     readOnlyHint?: bool|null,
     *     destructiveHint?: bool|null,
     *     idempotentHint?: bool|null,
     *     openWorldHint?: bool|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::asStringOrNull($data['title'] ?? null),
            self::asBoolOrNull($data['readOnlyHint'] ?? null),
            self::asBoolOrNull($data['destructiveHint'] ?? null),
            self::asBoolOrNull($data['idempotentHint'] ?? null),
            self::asBoolOrNull($data['openWorldHint'] ?? null)
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

        if ($this->title !== null) {
            $result['title'] = $this->title;
        }
        if ($this->readOnlyHint !== null) {
            $result['readOnlyHint'] = $this->readOnlyHint;
        }
        if ($this->destructiveHint !== null) {
            $result['destructiveHint'] = $this->destructiveHint;
        }
        if ($this->idempotentHint !== null) {
            $result['idempotentHint'] = $this->idempotentHint;
        }
        if ($this->openWorldHint !== null) {
            $result['openWorldHint'] = $this->openWorldHint;
        }

        return $result;
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @return bool|null
     */
    public function getReadOnlyHint(): ?bool
    {
        return $this->readOnlyHint;
    }

    /**
     * @return bool|null
     */
    public function getDestructiveHint(): ?bool
    {
        return $this->destructiveHint;
    }

    /**
     * @return bool|null
     */
    public function getIdempotentHint(): ?bool
    {
        return $this->idempotentHint;
    }

    /**
     * @return bool|null
     */
    public function getOpenWorldHint(): ?bool
    {
        return $this->openWorldHint;
    }
}
