<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\Union;

/**
 * Union type members:
 * - PingRequest
 * - InitializeRequest
 * - CompleteRequest
 * - SetLevelRequest
 * - GetPromptRequest
 * - ListPromptsRequest
 * - ListResourcesRequest
 * - ListResourceTemplatesRequest
 * - ReadResourceRequest
 * - SubscribeRequest
 * - UnsubscribeRequest
 * - CallToolRequest
 * - ListToolsRequest
 * - GetTaskRequest
 * - GetTaskPayloadRequest
 * - ListTasksRequest
 * - CancelTaskRequest
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
interface ClientRequestInterface
{
    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
