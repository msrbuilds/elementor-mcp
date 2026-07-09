<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Tasks\DTO;

use WP\McpSchema\Common\Protocol\DTO\Result;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * A response to a task-augmented request.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Client
 * @mcp-subdomain Tasks
 * @mcp-version 2025-11-25
 */
class CreateTaskResult extends Result
{
    use ValidatesRequiredFields;

    /**
     * @since 2025-11-25
     *
     * @var \WP\McpSchema\Client\Tasks\DTO\Task
     */
    protected Task $task;

    /**
     * @param \WP\McpSchema\Client\Tasks\DTO\Task $task @since 2025-11-25
     * @param array<string, mixed>|null $_meta @since 2025-11-25
     */
    public function __construct(
        Task $task,
        ?array $_meta = null
    ) {
        parent::__construct($_meta);
        $this->task = $task;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|null,
     *     task: array<string, mixed>|\WP\McpSchema\Client\Tasks\DTO\Task
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['task']);

        /** @var \WP\McpSchema\Client\Tasks\DTO\Task $task */
        $task = is_array($data['task'])
            ? Task::fromArray(self::asArray($data['task']))
            : $data['task'];

        return new self(
            $task,
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
        $result = parent::toArray();

        $result['task'] = $this->task->toArray();

        return $result;
    }

    /**
     * @return \WP\McpSchema\Client\Tasks\DTO\Task
     */
    public function getTask(): Task
    {
        return $this->task;
    }
}
