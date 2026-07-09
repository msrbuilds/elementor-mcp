<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Logging\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\JSONRPCNotification;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;
use WP\McpSchema\Server\Lifecycle\Union\ServerNotificationInterface;

/**
 * JSONRPCNotification of a log message passed from server to client. If no logging/setLevel request has been sent from the client, the server MAY decide which messages to send automatically.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: params)
 *
 * @mcp-domain Server
 * @mcp-subdomain Logging
 * @mcp-version 2025-11-25
 */
class LoggingMessageNotification extends JSONRPCNotification implements ServerNotificationInterface
{
    use ValidatesRequiredFields;

    public const METHOD = 'notifications/message';

    public const DISCRIMINATOR_FIELD = 'method';
    public const DISCRIMINATOR_VALUE = 'notifications/message';

    /**
     * @var \WP\McpSchema\Server\Logging\DTO\LoggingMessageNotificationParams
     */
    protected LoggingMessageNotificationParams $typedParams;

    /**
     * @param '2.0' $jsonrpc @since 2025-11-25
     * @param \WP\McpSchema\Server\Logging\DTO\LoggingMessageNotificationParams $params @since 2024-11-05
     */
    public function __construct(
        string $jsonrpc,
        LoggingMessageNotificationParams $params
    ) {
        parent::__construct(self::METHOD, $jsonrpc, null);
        $this->typedParams = $params;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     jsonrpc: '2.0',
     *     method: 'notifications/message',
     *     params: array<string, mixed>|\WP\McpSchema\Server\Logging\DTO\LoggingMessageNotificationParams
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['jsonrpc', 'params']);

        /** @var '2.0' $jsonrpc */
        $jsonrpc = self::asString($data['jsonrpc']);

        /** @var \WP\McpSchema\Server\Logging\DTO\LoggingMessageNotificationParams $params */
        $params = is_array($data['params'])
            ? LoggingMessageNotificationParams::fromArray(self::asArray($data['params']))
            : $data['params'];

        return new self(
            $jsonrpc,
            $params
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

        $result['params'] = $this->typedParams->toArray();

        return $result;
    }

    /**
     * @return \WP\McpSchema\Server\Logging\DTO\LoggingMessageNotificationParams
     */
    public function getTypedParams(): LoggingMessageNotificationParams
    {
        return $this->typedParams;
    }
}
