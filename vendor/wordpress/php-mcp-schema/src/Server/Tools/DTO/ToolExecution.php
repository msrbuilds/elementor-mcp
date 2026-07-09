<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Tools\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Execution-related properties for a tool.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Server
 * @mcp-subdomain Tools
 * @mcp-version 2025-11-25
 */
class ToolExecution extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * Indicates whether this tool supports task-augmented execution.
     * This allows clients to handle long-running operations through polling
     * the task system.
     *
     * - "forbidden": Tool does not support task-augmented execution (default when absent)
     * - "optional": Tool may support task-augmented execution
     * - "required": Tool requires task-augmented execution
     *
     * Default: "forbidden"
     *
     * @since 2025-11-25
     *
     * @var 'forbidden'|'optional'|'required'|null
     */
    protected ?string $taskSupport;

    /**
     * @param 'forbidden'|'optional'|'required'|null $taskSupport @since 2025-11-25
     */
    public function __construct(
        ?string $taskSupport = null
    ) {
        $this->taskSupport = $taskSupport;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     taskSupport?: 'forbidden'|'optional'|'required'|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        /** @var 'forbidden'|'optional'|'required'|null $taskSupport */
        $taskSupport = isset($data['taskSupport'])
            ? self::asStringOrNull($data['taskSupport'])
            : null;

        return new self(
            $taskSupport
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

        if ($this->taskSupport !== null) {
            $result['taskSupport'] = $this->taskSupport;
        }

        return $result;
    }

    /**
     * @return 'forbidden'|'optional'|'required'|null
     */
    public function getTaskSupport(): ?string
    {
        return $this->taskSupport;
    }
}
