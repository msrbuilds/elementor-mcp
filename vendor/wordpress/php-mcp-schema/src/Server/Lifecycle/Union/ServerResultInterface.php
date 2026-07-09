<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Lifecycle\Union;

/**
 * Union type members:
 * - EmptyResult
 * - InitializeResult
 * - CompleteResult
 * - GetPromptResult
 * - ListPromptsResult
 * - ListResourceTemplatesResult
 * - ListResourcesResult
 * - ReadResourceResult
 * - CallToolResult
 * - ListToolsResult
 * - GetTaskResult
 * - GetTaskPayloadResult
 * - ListTasksResult
 * - CancelTaskResult
 *
 * @mcp-domain Server
 * @mcp-subdomain Lifecycle
 * @mcp-version 2025-11-25
 */
interface ServerResultInterface
{
    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
