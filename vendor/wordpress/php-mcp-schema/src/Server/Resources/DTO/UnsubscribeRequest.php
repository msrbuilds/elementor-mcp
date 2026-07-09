<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Resources\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\JSONRPCRequest;
use WP\McpSchema\Common\Protocol\Union\ClientRequestInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Sent from the client to request cancellation of resources/updated notifications from the server. This should follow a previous resources/subscribe request.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: params)
 *
 * @mcp-domain Server
 * @mcp-subdomain Resources
 * @mcp-version 2025-11-25
 */
class UnsubscribeRequest extends JSONRPCRequest implements ClientRequestInterface
{
    use ValidatesRequiredFields;

    public const METHOD = 'resources/unsubscribe';

    public const DISCRIMINATOR_FIELD = 'method';
    public const DISCRIMINATOR_VALUE = 'resources/unsubscribe';

    /**
     * @var \WP\McpSchema\Server\Resources\DTO\UnsubscribeRequestParams
     */
    protected UnsubscribeRequestParams $typedParams;

    /**
     * @param '2.0' $jsonrpc @since 2025-11-25
     * @param string|number $id @since 2025-11-25
     * @param \WP\McpSchema\Server\Resources\DTO\UnsubscribeRequestParams $params @since 2024-11-05
     */
    public function __construct(
        string $jsonrpc,
        $id,
        UnsubscribeRequestParams $params
    ) {
        parent::__construct(self::METHOD, $jsonrpc, $id, null);
        $this->typedParams = $params;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     jsonrpc: '2.0',
     *     id: string|number,
     *     method: 'resources/unsubscribe',
     *     params: array<string, mixed>|\WP\McpSchema\Server\Resources\DTO\UnsubscribeRequestParams
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['jsonrpc', 'id', 'params']);

        /** @var '2.0' $jsonrpc */
        $jsonrpc = self::asString($data['jsonrpc']);

        /** @var string|number $id */
        $id = self::asStringOrNumber($data['id']);

        /** @var \WP\McpSchema\Server\Resources\DTO\UnsubscribeRequestParams $params */
        $params = is_array($data['params'])
            ? UnsubscribeRequestParams::fromArray(self::asArray($data['params']))
            : $data['params'];

        return new self(
            $jsonrpc,
            $id,
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
     * @return \WP\McpSchema\Server\Resources\DTO\UnsubscribeRequestParams
     */
    public function getTypedParams(): UnsubscribeRequestParams
    {
        return $this->typedParams;
    }
}
