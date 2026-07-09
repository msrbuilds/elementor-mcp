<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Lifecycle\Union;

/**
 * Union type members:
 * - EmptyResult
 * - CreateMessageResult
 * - ListRootsResult
 * - ElicitResult
 * - GetTaskResult
 * - GetTaskPayloadResult
 * - ListTasksResult
 * - CancelTaskResult
 *
 * @mcp-domain Client
 * @mcp-subdomain Lifecycle
 * @mcp-version 2025-11-25
 */
interface ClientResultInterface
{
    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
