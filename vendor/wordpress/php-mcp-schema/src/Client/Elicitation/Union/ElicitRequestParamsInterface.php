<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\Union;

/**
 * The parameters for a request to elicit additional information from the user via the client.
 *
 * Union type members:
 * - ElicitRequestFormParams
 * - ElicitRequestURLParams
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
interface ElicitRequestParamsInterface
{
    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
