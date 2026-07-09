<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\Union;

/**
 * Union type members:
 * - CancelledNotification
 * - ProgressNotification
 * - InitializedNotification
 * - RootsListChangedNotification
 * - TaskStatusNotification
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
interface ClientNotificationInterface
{
    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
