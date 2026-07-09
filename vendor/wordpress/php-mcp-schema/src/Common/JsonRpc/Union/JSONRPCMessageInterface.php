<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\JsonRpc\Union;

/**
 * Refers to any valid JSON-RPC object that can be decoded off the wire, or encoded to be sent.
 *
 * Union type members:
 * - JSONRPCRequest
 * - JSONRPCNotification
 * - JSONRPCResponse
 *
 * @mcp-domain Common
 * @mcp-subdomain JsonRpc
 * @mcp-version 2025-11-25
 */
interface JSONRPCMessageInterface
{
    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
