<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Logging\Enum;

use WP\McpSchema\Common\AbstractEnum;

/**
 * The severity of a log message.
 *
 * These map to syslog message severities, as specified in RFC-5424:
 * https://datatracker.ietf.org/doc/html/rfc5424#section-6.2.1
 *
 * @mcp-domain Server
 * @mcp-subdomain Logging
 * @mcp-version 2025-11-25
 */
final class LoggingLevel extends AbstractEnum
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const NOTICE = 'notice';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const CRITICAL = 'critical';
    public const ALERT = 'alert';
    public const EMERGENCY = 'emergency';

    public static function debug(): self
    {
        return self::from('debug');
    }

    public static function info(): self
    {
        return self::from('info');
    }

    public static function notice(): self
    {
        return self::from('notice');
    }

    public static function warning(): self
    {
        return self::from('warning');
    }

    public static function error(): self
    {
        return self::from('error');
    }

    public static function critical(): self
    {
        return self::from('critical');
    }

    public static function alert(): self
    {
        return self::from('alert');
    }

    public static function emergency(): self
    {
        return self::from('emergency');
    }

    /**
     * Returns all valid values for this enum.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return [
            self::DEBUG,
            self::INFO,
            self::NOTICE,
            self::WARNING,
            self::ERROR,
            self::CRITICAL,
            self::ALERT,
            self::EMERGENCY,
        ];
    }
}
