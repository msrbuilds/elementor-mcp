<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Lifecycle\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Present if the client supports sampling from an LLM.
 *
 * @mcp-domain Client
 * @mcp-subdomain Lifecycle
 * @mcp-version 2025-11-25
 */
class ClientCapabilitiesSampling extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * Whether the client supports context inclusion via includeContext parameter. If not declared, servers SHOULD only use `includeContext: "none"` (or omit it).
     *
     * @var object|null
     */
    protected ?object $context;

    /**
     * Whether the client supports tool use via tools and toolChoice parameters.
     *
     * @var object|null
     */
    protected ?object $tools;

    /**
     * @param object|null $context
     * @param object|null $tools
     */
    public function __construct(
        ?object $context = null,
        ?object $tools = null
    ) {
        $this->context = $context;
        $this->tools = $tools;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     context?: object|null,
     *     tools?: object|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::asObjectOrNull($data['context'] ?? null),
            self::asObjectOrNull($data['tools'] ?? null)
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

        if ($this->context !== null) {
            $result['context'] = $this->context;
        }
        if ($this->tools !== null) {
            $result['tools'] = $this->tools;
        }

        return $result;
    }

    /**
     * @return object|null
     */
    public function getContext(): ?object
    {
        return $this->context;
    }

    /**
     * @return object|null
     */
    public function getTools(): ?object
    {
        return $this->tools;
    }
}
