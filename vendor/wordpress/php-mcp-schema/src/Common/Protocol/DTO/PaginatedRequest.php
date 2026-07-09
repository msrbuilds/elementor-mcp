<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\JSONRPCRequest;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: params)
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class PaginatedRequest extends JSONRPCRequest
{
    use ValidatesRequiredFields;

    /**
     * @var \WP\McpSchema\Common\Protocol\DTO\PaginatedRequestParams|null
     */
    protected ?PaginatedRequestParams $typedParams;

    /**
     * @param '2.0' $jsonrpc @since 2025-11-25
     * @param string|number $id @since 2025-11-25
     * @param string $method @since 2024-11-05
     * @param \WP\McpSchema\Common\Protocol\DTO\PaginatedRequestParams|null $params @since 2024-11-05
     */
    public function __construct(
        string $jsonrpc,
        $id,
        string $method,
        ?PaginatedRequestParams $params = null
    ) {
        parent::__construct($method, $jsonrpc, $id, null);
        $this->typedParams = $params;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     jsonrpc: '2.0',
     *     id: string|number,
     *     method: string,
     *     params?: array<string, mixed>|\WP\McpSchema\Common\Protocol\DTO\PaginatedRequestParams|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['jsonrpc', 'id', 'method']);

        /** @var '2.0' $jsonrpc */
        $jsonrpc = self::asString($data['jsonrpc']);

        /** @var string|number $id */
        $id = self::asStringOrNumber($data['id']);

        /** @var \WP\McpSchema\Common\Protocol\DTO\PaginatedRequestParams|null $params */
        $params = isset($data['params'])
            ? (is_array($data['params'])
                ? PaginatedRequestParams::fromArray(self::asArray($data['params']))
                : $data['params'])
            : null;

        return new self(
            $jsonrpc,
            $id,
            self::asString($data['method']),
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
     * @return \WP\McpSchema\Common\Protocol\DTO\PaginatedRequestParams|null
     */
    public function getTypedParams(): ?PaginatedRequestParams
    {
        return $this->typedParams;
    }
}
