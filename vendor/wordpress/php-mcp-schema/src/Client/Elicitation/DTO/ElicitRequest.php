<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\DTO;

use WP\McpSchema\Client\Elicitation\Factory\ElicitRequestParamsFactory;
use WP\McpSchema\Client\Elicitation\Union\ElicitRequestParamsInterface;
use WP\McpSchema\Common\JsonRpc\DTO\JSONRPCRequest;
use WP\McpSchema\Common\Protocol\Union\ServerRequestInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * A request from the server to elicit additional information from the user via the client.
 *
 * @since 2025-06-18
 * @last-updated 2025-11-25 (modified property: params)
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
class ElicitRequest extends JSONRPCRequest implements ServerRequestInterface
{
    use ValidatesRequiredFields;

    public const METHOD = 'elicitation/create';

    public const DISCRIMINATOR_FIELD = 'method';
    public const DISCRIMINATOR_VALUE = 'elicitation/create';

    /**
     * @var \WP\McpSchema\Client\Elicitation\Union\ElicitRequestParamsInterface
     */
    protected ElicitRequestParamsInterface $typedParams;

    /**
     * @param '2.0' $jsonrpc @since 2025-11-25
     * @param string|number $id @since 2025-11-25
     * @param \WP\McpSchema\Client\Elicitation\Union\ElicitRequestParamsInterface $params @since 2025-06-18
     */
    public function __construct(
        string $jsonrpc,
        $id,
        ElicitRequestParamsInterface $params
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
     *     method: 'elicitation/create',
     *     params: array<string, mixed>|\WP\McpSchema\Client\Elicitation\Union\ElicitRequestParamsInterface
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

        /** @var \WP\McpSchema\Client\Elicitation\Union\ElicitRequestParamsInterface $params */
        $params = is_array($data['params'])
            ? ElicitRequestParamsFactory::fromArray(self::asArray($data['params']))
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
     * @return \WP\McpSchema\Client\Elicitation\Union\ElicitRequestParamsInterface
     */
    public function getTypedParams(): ElicitRequestParamsInterface
    {
        return $this->typedParams;
    }
}
