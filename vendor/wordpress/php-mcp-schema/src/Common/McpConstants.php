<?php

declare(strict_types=1);

namespace WP\McpSchema\Common;

/**
 * MCP Protocol Constants.
 *
 * Contains all exported constants from the MCP TypeScript schema including:
 * - Protocol version constants
 * - JSON-RPC version
 * - Standard JSON-RPC error codes
 * - MCP-specific error codes
 *
 * @since 2025-11-25
 *
 * @mcp-version 2025-11-25
 */
final class McpConstants
{
    // Protocol constants
    public const LATEST_PROTOCOL_VERSION = '2025-11-25';
    public const JSONRPC_VERSION = '2.0';

    // Error codes
    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const INTERNAL_ERROR = -32603;
    public const URL_ELICITATION_REQUIRED = -32042;

    /**
     * Returns all error codes defined in this class.
     *
     * @return int[]
     */
    public static function getErrorCodes(): array
    {
        return [
            self::PARSE_ERROR,
            self::INVALID_REQUEST,
            self::METHOD_NOT_FOUND,
            self::INVALID_PARAMS,
            self::INTERNAL_ERROR,
            self::URL_ELICITATION_REQUIRED,
        ];
    }

    /**
     * Returns error code names mapped to their values.
     *
     * @return array<string, int>
     */
    public static function getErrorCodeNames(): array
    {
        return [
            'PARSE_ERROR' => self::PARSE_ERROR,
            'INVALID_REQUEST' => self::INVALID_REQUEST,
            'METHOD_NOT_FOUND' => self::METHOD_NOT_FOUND,
            'INVALID_PARAMS' => self::INVALID_PARAMS,
            'INTERNAL_ERROR' => self::INTERNAL_ERROR,
            'URL_ELICITATION_REQUIRED' => self::URL_ELICITATION_REQUIRED,
        ];
    }

    /**
     * Checks if the given error code is valid.
     *
     * @param int $code
     * @return bool
     */
    public static function isValidErrorCode(int $code): bool
    {
        return in_array($code, self::getErrorCodes(), true);
    }

    /**
     * Gets the constant name for an error code.
     *
     * @param int $code
     * @return string|null The constant name, or null if not found
     */
    public static function getErrorCodeName(int $code): ?string
    {
        $flipped = array_flip(self::getErrorCodeNames());
        return $flipped[$code] ?? null;
    }

    /**
     * Checks if an error code is a standard JSON-RPC error.
     *
     * Standard JSON-RPC errors are in the range -32700 to -32600.
     *
     * @param int $code
     * @return bool
     */
    public static function isStandardJsonRpcError(int $code): bool
    {
        return $code >= -32700 && $code <= -32600;
    }
}
