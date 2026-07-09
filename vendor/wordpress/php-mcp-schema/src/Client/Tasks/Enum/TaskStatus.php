<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Tasks\Enum;

use WP\McpSchema\Common\AbstractEnum;

/**
 * The status of a task.
 *
 * @mcp-domain Client
 * @mcp-subdomain Tasks
 * @mcp-version 2025-11-25
 */
final class TaskStatus extends AbstractEnum
{
    public const WORKING = 'working';
    public const INPUT_REQUIRED = 'input_required';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    public static function working(): self
    {
        return self::from('working');
    }

    public static function inputRequired(): self
    {
        return self::from('input_required');
    }

    public static function completed(): self
    {
        return self::from('completed');
    }

    public static function failed(): self
    {
        return self::from('failed');
    }

    public static function cancelled(): self
    {
        return self::from('cancelled');
    }

    /**
     * Returns all valid values for this enum.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return [
            self::WORKING,
            self::INPUT_REQUIRED,
            self::COMPLETED,
            self::FAILED,
            self::CANCELLED,
        ];
    }
}
