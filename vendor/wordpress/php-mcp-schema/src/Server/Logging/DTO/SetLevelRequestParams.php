<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Logging\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\RequestParams;
use WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Parameters for a `logging/setLevel` request.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Server
 * @mcp-subdomain Logging
 * @mcp-version 2025-11-25
 */
class SetLevelRequestParams extends RequestParams
{
    use ValidatesRequiredFields;

    /**
     * The level of logging that the client wants to receive from the server. The server should send all logs at this level and higher (i.e., more severe) to the client as notifications/message.
     *
     * @since 2025-11-25
     *
     * @var 'debug'|'info'|'notice'|'warning'|'error'|'critical'|'alert'|'emergency'
     */
    protected string $level;

    /**
     * @param 'debug'|'info'|'notice'|'warning'|'error'|'critical'|'alert'|'emergency' $level @since 2025-11-25
     * @param \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta @since 2025-11-25
     */
    public function __construct(
        string $level,
        ?RequestParamsMeta $_meta = null
    ) {
        parent::__construct($_meta);
        $this->level = $level;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|\WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null,
     *     level: 'debug'|'info'|'notice'|'warning'|'error'|'critical'|'alert'|'emergency'
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['level']);

        /** @var 'debug'|'info'|'notice'|'warning'|'error'|'critical'|'alert'|'emergency' $level */
        $level = self::asString($data['level']);

        /** @var \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta */
        $_meta = isset($data['_meta'])
            ? (is_array($data['_meta'])
                ? RequestParamsMeta::fromArray(self::asArray($data['_meta']))
                : $data['_meta'])
            : null;

        return new self(
            $level,
            $_meta
        );
    }

    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = parent::toArray();

        $result['level'] = $this->level;

        return $result;
    }

    /**
     * @return 'debug'|'info'|'notice'|'warning'|'error'|'critical'|'alert'|'emergency'
     */
    public function getLevel(): string
    {
        return $this->level;
    }
}
