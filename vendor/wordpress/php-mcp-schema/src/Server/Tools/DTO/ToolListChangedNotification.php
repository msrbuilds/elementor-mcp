<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Tools\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\JSONRPCNotification;
use WP\McpSchema\Common\JsonRpc\DTO\NotificationParams;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;
use WP\McpSchema\Server\Lifecycle\Union\ServerNotificationInterface;

/**
 * An optional notification from the server to the client, informing it that the list of tools it offers has changed. This may be issued by servers without any previous subscription from the client.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: params)
 *
 * @mcp-domain Server
 * @mcp-subdomain Tools
 * @mcp-version 2025-11-25
 */
class ToolListChangedNotification extends JSONRPCNotification implements ServerNotificationInterface
{
    use ValidatesRequiredFields;

    public const METHOD = 'notifications/tools/list_changed';

    public const DISCRIMINATOR_FIELD = 'method';
    public const DISCRIMINATOR_VALUE = 'notifications/tools/list_changed';

    /**
     * @var \WP\McpSchema\Common\JsonRpc\DTO\NotificationParams|null
     */
    protected ?NotificationParams $typedParams;

    /**
     * @param '2.0' $jsonrpc @since 2025-11-25
     * @param \WP\McpSchema\Common\JsonRpc\DTO\NotificationParams|null $params @since 2024-11-05
     */
    public function __construct(
        string $jsonrpc,
        ?NotificationParams $params = null
    ) {
        parent::__construct(self::METHOD, $jsonrpc, null);
        $this->typedParams = $params;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     jsonrpc: '2.0',
     *     method: 'notifications/tools/list_changed',
     *     params?: array<string, mixed>|\WP\McpSchema\Common\JsonRpc\DTO\NotificationParams|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['jsonrpc']);

        /** @var '2.0' $jsonrpc */
        $jsonrpc = self::asString($data['jsonrpc']);

        /** @var \WP\McpSchema\Common\JsonRpc\DTO\NotificationParams|null $params */
        $params = isset($data['params'])
            ? (is_array($data['params'])
                ? NotificationParams::fromArray(self::asArray($data['params']))
                : $data['params'])
            : null;

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

        if ($this->typedParams !== null) {
            $result['params'] = $this->typedParams->toArray();
        }

        return $result;
    }

    /**
     * @return \WP\McpSchema\Common\JsonRpc\DTO\NotificationParams|null
     */
    public function getTypedParams(): ?NotificationParams
    {
        return $this->typedParams;
    }
}
