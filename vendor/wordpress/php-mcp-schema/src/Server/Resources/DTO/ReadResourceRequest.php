<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Resources\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\JSONRPCRequest;
use WP\McpSchema\Common\Protocol\Union\ClientRequestInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Sent from the client to the server, to read a specific resource URI.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: params)
 *
 * @mcp-domain Server
 * @mcp-subdomain Resources
 * @mcp-version 2025-11-25
 */
class ReadResourceRequest extends JSONRPCRequest implements ClientRequestInterface
{
    use ValidatesRequiredFields;

    public const METHOD = 'resources/read';

    public const DISCRIMINATOR_FIELD = 'method';
    public const DISCRIMINATOR_VALUE = 'resources/read';

    /**
     * @var \WP\McpSchema\Server\Resources\DTO\ReadResourceRequestParams
     */
    protected ReadResourceRequestParams $typedParams;

    /**
     * @param '2.0' $jsonrpc @since 2025-11-25
     * @param string|number $id @since 2025-11-25
     * @param \WP\McpSchema\Server\Resources\DTO\ReadResourceRequestParams $params @since 2024-11-05
     */
    public function __construct(
        string $jsonrpc,
        $id,
        ReadResourceRequestParams $params
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
     *     method: 'resources/read',
     *     params: array<string, mixed>|\WP\McpSchema\Server\Resources\DTO\ReadResourceRequestParams
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

        /** @var \WP\McpSchema\Server\Resources\DTO\ReadResourceRequestParams $params */
        $params = is_array($data['params'])
            ? ReadResourceRequestParams::fromArray(self::asArray($data['params']))
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
     * @return \WP\McpSchema\Server\Resources\DTO\ReadResourceRequestParams
     */
    public function getTypedParams(): ReadResourceRequestParams
    {
        return $this->typedParams;
    }
}
