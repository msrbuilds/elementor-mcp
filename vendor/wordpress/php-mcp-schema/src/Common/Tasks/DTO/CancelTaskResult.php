<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Tasks\DTO;

use WP\McpSchema\Common\Protocol\DTO\Result;
use WP\McpSchema\Client\Lifecycle\Union\ClientResultInterface;
use WP\McpSchema\Server\Lifecycle\Union\ServerResultInterface;

/**
 * The response to a tasks/cancel request.
 *
 * This class represents an intersection type combining properties from:
 * - {@see Result}
 * - {@see Task}
 *
 * In TypeScript, this is defined as: `type CancelTaskResult = Result & Task`
 *
 * PHP requires actual classes for union interface implementation, so this class
 * extends Result and merges properties from: Task
 *
 * Implements union interfaces:
 * - {@see ClientResultInterface}
 * - {@see ServerResultInterface}
 *
 * @since 2025-11-25
 *
 * @mcp-domain Common
 * @mcp-subdomain Tasks
 * @mcp-version 2025-11-25
 */
class CancelTaskResult extends Result implements ClientResultInterface, ServerResultInterface
{
    /**
     * The task identifier.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $taskId;

    /**
     * Current task state.
     *
     * @since 2025-11-25
     *
     * @var 'working'|'input_required'|'completed'|'failed'|'cancelled'
     */
    protected string $status;

    /**
     * Optional human-readable message describing the current task state.
     * This can provide context for any status, including:
     * - Reasons for "cancelled" status
     * - Summaries for "completed" status
     * - Diagnostic information for "failed" status (e.g., error details, what went wrong)
     *
     * @since 2025-11-25
     *
     * @var string|null
     */
    protected ?string $statusMessage = null;

    /**
     * ISO 8601 timestamp when the task was created.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $createdAt;

    /**
     * ISO 8601 timestamp when the task was last updated.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $lastUpdatedAt;

    /**
     * Actual retention duration from creation in milliseconds, null for unlimited.
     *
     * @since 2025-11-25
     *
     * @var int|null
     */
    protected ?int $ttl = null;

    /**
     * Suggested polling interval in milliseconds.
     *
     * @since 2025-11-25
     *
     * @var int|null
     */
    protected ?int $pollInterval = null;

    /**
     * @param string $taskId @since 2025-11-25
     * @param 'working'|'input_required'|'completed'|'failed'|'cancelled' $status @since 2025-11-25
     * @param string $createdAt @since 2025-11-25
     * @param string $lastUpdatedAt @since 2025-11-25
     * @param int $ttl @since 2025-11-25
     * @param array<string, mixed>|null $_meta @since 2025-11-25
     * @param string|null $statusMessage @since 2025-11-25
     * @param int|null $pollInterval @since 2025-11-25
     */
    public function __construct(
        string $taskId,
        string $status,
        string $createdAt,
        string $lastUpdatedAt,
        int $ttl,
        ?array $_meta = null,
        ?string $statusMessage = null,
        ?int $pollInterval = null
    ) {
        parent::__construct($_meta);
        $this->taskId = $taskId;
        $this->status = $status;
        $this->statusMessage = $statusMessage;
        $this->createdAt = $createdAt;
        $this->lastUpdatedAt = $lastUpdatedAt;
        $this->ttl = $ttl;
        $this->pollInterval = $pollInterval;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['taskId', 'status', 'createdAt', 'lastUpdatedAt', 'ttl']);

        /** @var 'working'|'input_required'|'completed'|'failed'|'cancelled' $status */
        $status = self::asString($data['status']);

        return new self(
            self::asString($data['taskId']),
            $status,
            self::asString($data['createdAt']),
            self::asString($data['lastUpdatedAt']),
            self::asInt($data['ttl']),
            self::asArrayOrNull($data['_meta'] ?? null),
            self::asStringOrNull($data['statusMessage'] ?? null),
            self::asIntOrNull($data['pollInterval'] ?? null)
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

        $result['taskId'] = $this->taskId;
        $result['status'] = $this->status;
        if ($this->statusMessage !== null) {
            $result['statusMessage'] = $this->statusMessage;
        }
        $result['createdAt'] = $this->createdAt;
        $result['lastUpdatedAt'] = $this->lastUpdatedAt;
        $result['ttl'] = $this->ttl;
        if ($this->pollInterval !== null) {
            $result['pollInterval'] = $this->pollInterval;
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getTaskId(): string
    {
        return $this->taskId;
    }

    /**
     * @return 'working'|'input_required'|'completed'|'failed'|'cancelled'
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return string|null
     */
    public function getStatusMessage(): ?string
    {
        return $this->statusMessage;
    }

    /**
     * @return string
     */
    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    /**
     * @return string
     */
    public function getLastUpdatedAt(): string
    {
        return $this->lastUpdatedAt;
    }

    /**
     * @return int|null
     */
    public function getTtl(): ?int
    {
        return $this->ttl;
    }

    /**
     * @return int|null
     */
    public function getPollInterval(): ?int
    {
        return $this->pollInterval;
    }
}
