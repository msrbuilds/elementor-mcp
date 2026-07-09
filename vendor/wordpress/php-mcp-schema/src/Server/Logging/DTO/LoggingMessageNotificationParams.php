<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Logging\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\NotificationParams;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Parameters for a `notifications/message` notification.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Server
 * @mcp-subdomain Logging
 * @mcp-version 2025-11-25
 */
class LoggingMessageNotificationParams extends NotificationParams
{
    use ValidatesRequiredFields;

    /**
     * The severity of this log message.
     *
     * @since 2025-11-25
     *
     * @var 'debug'|'info'|'notice'|'warning'|'error'|'critical'|'alert'|'emergency'
     */
    protected string $level;

    /**
     * An optional name of the logger issuing this message.
     *
     * @since 2025-11-25
     *
     * @var string|null
     */
    protected ?string $logger;

    /**
     * The data to be logged, such as a string message or an object. Any JSON serializable type is allowed here.
     *
     * @since 2025-11-25
     *
     * @var mixed
     */
    protected $data;

    /**
     * @param 'debug'|'info'|'notice'|'warning'|'error'|'critical'|'alert'|'emergency' $level @since 2025-11-25
     * @param mixed $data @since 2025-11-25
     * @param array<string, mixed>|null $_meta @since 2025-11-25
     * @param string|null $logger @since 2025-11-25
     */
    public function __construct(
        string $level,
        $data,
        ?array $_meta = null,
        ?string $logger = null
    ) {
        parent::__construct($_meta);
        $this->level = $level;
        $this->data = $data;
        $this->logger = $logger;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|null,
     *     level: 'debug'|'info'|'notice'|'warning'|'error'|'critical'|'alert'|'emergency',
     *     logger?: string|null,
     *     data: mixed
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['level', 'data']);

        /** @var 'debug'|'info'|'notice'|'warning'|'error'|'critical'|'alert'|'emergency' $level */
        $level = self::asString($data['level']);

        return new self(
            $level,
            $data['data'],
            self::asArrayOrNull($data['_meta'] ?? null),
            self::asStringOrNull($data['logger'] ?? null)
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
        if ($this->logger !== null) {
            $result['logger'] = $this->logger;
        }
        $result['data'] = $this->data;

        return $result;
    }

    /**
     * @return 'debug'|'info'|'notice'|'warning'|'error'|'critical'|'alert'|'emergency'
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * @return string|null
     */
    public function getLogger(): ?string
    {
        return $this->logger;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }
}
