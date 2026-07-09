<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Prompts\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\JSONRPCRequest;
use WP\McpSchema\Common\Protocol\Union\ClientRequestInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Used by the client to get a prompt provided by the server.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: params)
 *
 * @mcp-domain Server
 * @mcp-subdomain Prompts
 * @mcp-version 2025-11-25
 */
class GetPromptRequest extends JSONRPCRequest implements ClientRequestInterface
{
    use ValidatesRequiredFields;

    public const METHOD = 'prompts/get';

    public const DISCRIMINATOR_FIELD = 'method';
    public const DISCRIMINATOR_VALUE = 'prompts/get';

    /**
     * @var \WP\McpSchema\Server\Prompts\DTO\GetPromptRequestParams
     */
    protected GetPromptRequestParams $typedParams;

    /**
     * @param '2.0' $jsonrpc @since 2025-11-25
     * @param string|number $id @since 2025-11-25
     * @param \WP\McpSchema\Server\Prompts\DTO\GetPromptRequestParams $params @since 2024-11-05
     */
    public function __construct(
        string $jsonrpc,
        $id,
        GetPromptRequestParams $params
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
     *     method: 'prompts/get',
     *     params: array<string, mixed>|\WP\McpSchema\Server\Prompts\DTO\GetPromptRequestParams
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

        /** @var \WP\McpSchema\Server\Prompts\DTO\GetPromptRequestParams $params */
        $params = is_array($data['params'])
            ? GetPromptRequestParams::fromArray(self::asArray($data['params']))
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
     * @return \WP\McpSchema\Server\Prompts\DTO\GetPromptRequestParams
     */
    public function getTypedParams(): GetPromptRequestParams
    {
        return $this->typedParams;
    }
}
