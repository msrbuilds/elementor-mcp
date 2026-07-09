<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\JSONRPCNotification;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;
use WP\McpSchema\Server\Lifecycle\Union\ServerNotificationInterface;

/**
 * An optional notification from the server to the client, informing it of a completion of a out-of-band elicitation request.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
class ElicitationCompleteNotification extends JSONRPCNotification implements ServerNotificationInterface
{
    use ValidatesRequiredFields;

    public const METHOD = 'notifications/elicitation/complete';

    public const DISCRIMINATOR_FIELD = 'method';
    public const DISCRIMINATOR_VALUE = 'notifications/elicitation/complete';

    /**
     * @var \WP\McpSchema\Client\Elicitation\DTO\ElicitationCompleteNotificationParams
     */
    protected ElicitationCompleteNotificationParams $typedParams;

    /**
     * @param '2.0' $jsonrpc @since 2025-11-25
     * @param \WP\McpSchema\Client\Elicitation\DTO\ElicitationCompleteNotificationParams $params @since 2025-11-25
     */
    public function __construct(
        string $jsonrpc,
        ElicitationCompleteNotificationParams $params
    ) {
        parent::__construct(self::METHOD, $jsonrpc, null);
        $this->typedParams = $params;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     jsonrpc: '2.0',
     *     method: 'notifications/elicitation/complete',
     *     params: array<string, mixed>|\WP\McpSchema\Client\Elicitation\DTO\ElicitationCompleteNotificationParams
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['jsonrpc', 'params']);

        /** @var '2.0' $jsonrpc */
        $jsonrpc = self::asString($data['jsonrpc']);

        /** @var \WP\McpSchema\Client\Elicitation\DTO\ElicitationCompleteNotificationParams $params */
        $params = is_array($data['params'])
            ? ElicitationCompleteNotificationParams::fromArray(self::asArray($data['params']))
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
     * @return \WP\McpSchema\Client\Elicitation\DTO\ElicitationCompleteNotificationParams
     */
    public function getTypedParams(): ElicitationCompleteNotificationParams
    {
        return $this->typedParams;
    }
}
