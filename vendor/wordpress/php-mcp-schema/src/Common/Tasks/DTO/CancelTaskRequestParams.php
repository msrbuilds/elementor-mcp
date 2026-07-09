<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Tasks\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * @mcp-domain Common
 * @mcp-subdomain Tasks
 * @mcp-version 2025-11-25
 */
class CancelTaskRequestParams extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * The task identifier to cancel.
     *
     * @var string
     */
    protected string $taskId;

    /**
     * @param string $taskId
     */
    public function __construct(
        string $taskId
    ) {
        $this->taskId = $taskId;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     taskId: string
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['taskId']);

        return new self(
            self::asString($data['taskId'])
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

        $result['taskId'] = $this->taskId;

        return $result;
    }

    /**
     * @return string
     */
    public function getTaskId(): string
    {
        return $this->taskId;
    }
}
