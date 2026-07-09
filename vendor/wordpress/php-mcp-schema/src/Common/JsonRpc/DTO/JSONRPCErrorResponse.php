<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\JsonRpc\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\JsonRpc\Union\JSONRPCResponseInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * A response to a request that indicates an error occurred.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Common
 * @mcp-subdomain JsonRpc
 * @mcp-version 2025-11-25
 */
class JSONRPCErrorResponse extends AbstractDataTransferObject implements JSONRPCResponseInterface
{
    use ValidatesRequiredFields;

    /**
     * @since 2025-11-25
     *
     * @var '2.0'
     */
    protected string $jsonrpc;

    /**
     * @since 2025-11-25
     *
     * @var string|number|null
     */
    protected $id;

    /**
     * @since 2025-11-25
     *
     * @var \WP\McpSchema\Common\JsonRpc\DTO\Error
     */
    protected Error $error;

    /**
     * @param '2.0' $jsonrpc @since 2025-11-25
     * @param \WP\McpSchema\Common\JsonRpc\DTO\Error $error @since 2025-11-25
     * @param string|number|null $id @since 2025-11-25
     */
    public function __construct(
        string $jsonrpc,
        Error $error,
        $id = null
    ) {
        $this->jsonrpc = $jsonrpc;
        $this->error = $error;
        $this->id = $id;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     jsonrpc: '2.0',
     *     id?: string|number|null,
     *     error: array<string, mixed>|\WP\McpSchema\Common\JsonRpc\DTO\Error
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['jsonrpc', 'error']);

        /** @var '2.0' $jsonrpc */
        $jsonrpc = self::asString($data['jsonrpc']);

        /** @var \WP\McpSchema\Common\JsonRpc\DTO\Error $error */
        $error = is_array($data['error'])
            ? Error::fromArray(self::asArray($data['error']))
            : $data['error'];

        /** @var string|number|null $id */
        $id = isset($data['id'])
            ? self::asStringOrNumberOrNull($data['id'])
            : null;

        return new self(
            $jsonrpc,
            $error,
            $id
        );
    }

    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        $result['jsonrpc'] = $this->jsonrpc;
        if ($this->id !== null) {
            $result['id'] = $this->id;
        }
        $result['error'] = $this->error->toArray();

        return $result;
    }

    /**
     * @return '2.0'
     */
    public function getJsonrpc(): string
    {
        return $this->jsonrpc;
    }

    /**
     * @return string|number|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return \WP\McpSchema\Common\JsonRpc\DTO\Error
     */
    public function getError(): Error
    {
        return $this->error;
    }
}
