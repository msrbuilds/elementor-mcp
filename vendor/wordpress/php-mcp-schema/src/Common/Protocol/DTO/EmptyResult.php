<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Client\Lifecycle\Union\ClientResultInterface;
use WP\McpSchema\Server\Lifecycle\Union\ServerResultInterface;

/**
 * A response that indicates success but carries no data.
 *
 * This class is a wrapper for {@see Result} that implements union interfaces.
 * In TypeScript, this is defined as: `type EmptyResult = Result`
 *
 * PHP requires actual classes for union interface implementation, so this wrapper
 * provides type-safe compatibility with:
 * - {@see ClientResultInterface}
 * - {@see ServerResultInterface}
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class EmptyResult extends Result implements ClientResultInterface, ServerResultInterface
{
    // Inherits all functionality from Result.
    // This wrapper exists solely to implement union interfaces for type safety.
}
