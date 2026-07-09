<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Tasks\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\JSONRPCRequest;
use WP\McpSchema\Common\Protocol\Union\ClientRequestInterface;
use WP\McpSchema\Common\Protocol\Union\ServerRequestInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * A request to retrieve the state of a task.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Common
 * @mcp-subdomain Tasks
 * @mcp-version 2025-11-25
 */
class GetTaskRequest extends JSONRPCRequest implements ClientRequestInterface, ServerRequestInterface
{
    use ValidatesRequiredFields;

    public const METHOD = 'tasks/get';

    public const DISCRIMINATOR_FIELD = 'method';
    public const DISCRIMINATOR_VALUE = 'tasks/get';

    /**
     * @var \WP\McpSchema\Common\Tasks\DTO\GetTaskRequestParams
     */
    protected GetTaskRequestParams $typedParams;

    /**
     * @param '2.0' $jsonrpc @since 2025-11-25
     * @param string|number $id @since 2025-11-25
     * @param \WP\McpSchema\Common\Tasks\DTO\GetTaskRequestParams $params @since 2025-11-25
     */
    public function __construct(
        string $jsonrpc,
        $id,
        GetTaskRequestParams $params
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
     *     method: 'tasks/get',
     *     params: array<string, mixed>|\WP\McpSchema\Common\Tasks\DTO\GetTaskRequestParams
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

        /** @var \WP\McpSchema\Common\Tasks\DTO\GetTaskRequestParams $params */
        $params = is_array($data['params'])
            ? GetTaskRequestParams::fromArray(self::asArray($data['params']))
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
     * @return \WP\McpSchema\Common\Tasks\DTO\GetTaskRequestParams
     */
    public function getTypedParams(): GetTaskRequestParams
    {
        return $this->typedParams;
    }
}
