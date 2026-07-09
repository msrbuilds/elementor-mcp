<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Roots\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\JSONRPCRequest;
use WP\McpSchema\Common\JsonRpc\DTO\RequestParams;
use WP\McpSchema\Common\Protocol\Union\ServerRequestInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Sent from the server to request a list of root URIs from the client. Roots allow
 * servers to ask for specific directories or files to operate on. A common example
 * for roots is providing a set of repositories or directories a server should operate
 * on.
 *
 * This request is typically used when the server needs to understand the file system
 * structure or access specific locations that the client has permission to read from.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: params)
 *
 * @mcp-domain Client
 * @mcp-subdomain Roots
 * @mcp-version 2025-11-25
 */
class ListRootsRequest extends JSONRPCRequest implements ServerRequestInterface
{
    use ValidatesRequiredFields;

    public const METHOD = 'roots/list';

    public const DISCRIMINATOR_FIELD = 'method';
    public const DISCRIMINATOR_VALUE = 'roots/list';

    /**
     * @var \WP\McpSchema\Common\JsonRpc\DTO\RequestParams|null
     */
    protected ?RequestParams $typedParams;

    /**
     * @param '2.0' $jsonrpc @since 2025-11-25
     * @param string|number $id @since 2025-11-25
     * @param \WP\McpSchema\Common\JsonRpc\DTO\RequestParams|null $params @since 2024-11-05
     */
    public function __construct(
        string $jsonrpc,
        $id,
        ?RequestParams $params = null
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
     *     method: 'roots/list',
     *     params?: array<string, mixed>|\WP\McpSchema\Common\JsonRpc\DTO\RequestParams|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['jsonrpc', 'id']);

        /** @var '2.0' $jsonrpc */
        $jsonrpc = self::asString($data['jsonrpc']);

        /** @var string|number $id */
        $id = self::asStringOrNumber($data['id']);

        /** @var \WP\McpSchema\Common\JsonRpc\DTO\RequestParams|null $params */
        $params = isset($data['params'])
            ? (is_array($data['params'])
                ? RequestParams::fromArray(self::asArray($data['params']))
                : $data['params'])
            : null;

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

        if ($this->typedParams !== null) {
            $result['params'] = $this->typedParams->toArray();
        }

        return $result;
    }

    /**
     * @return \WP\McpSchema\Common\JsonRpc\DTO\RequestParams|null
     */
    public function getTypedParams(): ?RequestParams
    {
        return $this->typedParams;
    }
}
