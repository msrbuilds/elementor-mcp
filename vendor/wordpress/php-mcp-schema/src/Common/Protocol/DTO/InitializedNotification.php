<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\JSONRPCNotification;
use WP\McpSchema\Common\JsonRpc\DTO\NotificationParams;
use WP\McpSchema\Common\Protocol\Union\ClientNotificationInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * This notification is sent from the client to the server after initialization has finished.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: params)
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class InitializedNotification extends JSONRPCNotification implements ClientNotificationInterface
{
    use ValidatesRequiredFields;

    public const METHOD = 'notifications/initialized';

    public const DISCRIMINATOR_FIELD = 'method';
    public const DISCRIMINATOR_VALUE = 'notifications/initialized';

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
     *     method: 'notifications/initialized',
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
