<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Sampling\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\JSONRPCRequest;
use WP\McpSchema\Common\Protocol\Union\ServerRequestInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * A request from the server to sample an LLM via the client. The client has full discretion over which model to select. The client should also inform the user before beginning sampling, to allow them to inspect the request (human in the loop) and decide whether to approve it.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: params)
 *
 * @mcp-domain Client
 * @mcp-subdomain Sampling
 * @mcp-version 2025-11-25
 */
class CreateMessageRequest extends JSONRPCRequest implements ServerRequestInterface
{
    use ValidatesRequiredFields;

    public const METHOD = 'sampling/createMessage';

    public const DISCRIMINATOR_FIELD = 'method';
    public const DISCRIMINATOR_VALUE = 'sampling/createMessage';

    /**
     * @var \WP\McpSchema\Client\Sampling\DTO\CreateMessageRequestParams
     */
    protected CreateMessageRequestParams $typedParams;

    /**
     * @param '2.0' $jsonrpc @since 2025-11-25
     * @param string|number $id @since 2025-11-25
     * @param \WP\McpSchema\Client\Sampling\DTO\CreateMessageRequestParams $params @since 2024-11-05
     */
    public function __construct(
        string $jsonrpc,
        $id,
        CreateMessageRequestParams $params
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
     *     method: 'sampling/createMessage',
     *     params: array<string, mixed>|\WP\McpSchema\Client\Sampling\DTO\CreateMessageRequestParams
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

        /** @var \WP\McpSchema\Client\Sampling\DTO\CreateMessageRequestParams $params */
        $params = is_array($data['params'])
            ? CreateMessageRequestParams::fromArray(self::asArray($data['params']))
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
     * @return \WP\McpSchema\Client\Sampling\DTO\CreateMessageRequestParams
     */
    public function getTypedParams(): CreateMessageRequestParams
    {
        return $this->typedParams;
    }
}
