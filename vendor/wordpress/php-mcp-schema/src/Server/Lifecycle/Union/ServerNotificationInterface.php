<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Lifecycle\Union;

/**
 * Union type members:
 * - CancelledNotification
 * - ProgressNotification
 * - LoggingMessageNotification
 * - ResourceUpdatedNotification
 * - ResourceListChangedNotification
 * - ToolListChangedNotification
 * - PromptListChangedNotification
 * - ElicitationCompleteNotification
 * - TaskStatusNotification
 *
 * @mcp-domain Server
 * @mcp-subdomain Lifecycle
 * @mcp-version 2025-11-25
 */
interface ServerNotificationInterface
{
    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
