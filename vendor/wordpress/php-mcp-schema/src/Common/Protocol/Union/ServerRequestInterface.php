<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\Union;

/**
 * Union type members:
 * - PingRequest
 * - CreateMessageRequest
 * - ListRootsRequest
 * - ElicitRequest
 * - GetTaskRequest
 * - GetTaskPayloadRequest
 * - ListTasksRequest
 * - CancelTaskRequest
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
interface ServerRequestInterface
{
    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
