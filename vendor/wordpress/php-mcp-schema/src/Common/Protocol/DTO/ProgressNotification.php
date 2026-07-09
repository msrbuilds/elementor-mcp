<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\JSONRPCNotification;
use WP\McpSchema\Common\Protocol\Union\ClientNotificationInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;
use WP\McpSchema\Server\Lifecycle\Union\ServerNotificationInterface;

/**
 * An out-of-band notification used to inform the receiver of a progress update for a long-running request.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: params)
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class ProgressNotification extends JSONRPCNotification implements ClientNotificationInterface, ServerNotificationInterface
{
    use ValidatesRequiredFields;

    public const METHOD = 'notifications/progress';

    public const DISCRIMINATOR_FIELD = 'method';
    public const DISCRIMINATOR_VALUE = 'notifications/progress';

    /**
     * @var \WP\McpSchema\Common\Protocol\DTO\ProgressNotificationParams
     */
    protected ProgressNotificationParams $typedParams;

    /**
     * @param '2.0' $jsonrpc @since 2025-11-25
     * @param \WP\McpSchema\Common\Protocol\DTO\ProgressNotificationParams $params @since 2024-11-05
     */
    public function __construct(
        string $jsonrpc,
        ProgressNotificationParams $params
    ) {
        parent::__construct(self::METHOD, $jsonrpc, null);
        $this->typedParams = $params;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     jsonrpc: '2.0',
     *     method: 'notifications/progress',
     *     params: array<string, mixed>|\WP\McpSchema\Common\Protocol\DTO\ProgressNotificationParams
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['jsonrpc', 'params']);

        /** @var '2.0' $jsonrpc */
        $jsonrpc = self::asString($data['jsonrpc']);

        /** @var \WP\McpSchema\Common\Protocol\DTO\ProgressNotificationParams $params */
        $params = is_array($data['params'])
            ? ProgressNotificationParams::fromArray(self::asArray($data['params']))
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
     * @return \WP\McpSchema\Common\Protocol\DTO\ProgressNotificationParams
     */
    public function getTypedParams(): ProgressNotificationParams
    {
        return $this->typedParams;
    }
}
