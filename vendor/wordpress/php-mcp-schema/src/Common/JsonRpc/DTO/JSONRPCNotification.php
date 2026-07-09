<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\JsonRpc\DTO;

use WP\McpSchema\Common\JsonRpc\Union\JSONRPCMessageInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * A notification which does not expect a response.
 *
 * @since 2024-11-05
 *
 * @mcp-domain Common
 * @mcp-subdomain JsonRpc
 * @mcp-version 2025-11-25
 */
class JSONRPCNotification extends Notification implements JSONRPCMessageInterface
{
    use ValidatesRequiredFields;

    /**
     * @since 2024-11-05
     *
     * @var '2.0'
     */
    protected string $jsonrpc;

    /**
     * @param string $method @since 2024-11-05
     * @param '2.0' $jsonrpc @since 2024-11-05
     * @param array<string, mixed>|null $params @since 2024-11-05
     */
    public function __construct(
        string $method,
        string $jsonrpc,
        ?array $params = null
    ) {
        parent::__construct($method, $params);
        $this->jsonrpc = $jsonrpc;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     method: string,
     *     params?: array<string, mixed>|null,
     *     jsonrpc: '2.0'
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['method', 'jsonrpc']);

        /** @var '2.0' $jsonrpc */
        $jsonrpc = self::asString($data['jsonrpc']);

        return new self(
            self::asString($data['method']),
            $jsonrpc,
            self::asArrayOrNull($data['params'] ?? null)
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

        $result['jsonrpc'] = $this->jsonrpc;

        return $result;
    }

    /**
     * @return '2.0'
     */
    public function getJsonrpc(): string
    {
        return $this->jsonrpc;
    }
}
