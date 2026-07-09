<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\Enum;

use WP\McpSchema\Common\AbstractEnum;

/**
 * The sender or recipient of messages and data in a conversation.
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
final class Role extends AbstractEnum
{
    public const USER = 'user';
    public const ASSISTANT = 'assistant';

    public static function user(): self
    {
        return self::from('user');
    }

    public static function assistant(): self
    {
        return self::from('assistant');
    }

    /**
     * Returns all valid values for this enum.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return [
            self::USER,
            self::ASSISTANT,
        ];
    }
}
