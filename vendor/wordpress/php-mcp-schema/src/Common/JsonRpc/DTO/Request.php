<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\JsonRpc\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * @since 2024-11-05
 *
 * @mcp-domain Common
 * @mcp-subdomain JsonRpc
 * @mcp-version 2025-11-25
 */
class Request extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * @since 2024-11-05
     *
     * @var string
     */
    protected string $method;

    /**
     * @since 2024-11-05
     *
     * @var array<string, mixed>|null
     */
    protected ?array $params;

    /**
     * @param string $method @since 2024-11-05
     * @param array<string, mixed>|null $params @since 2024-11-05
     */
    public function __construct(
        string $method,
        ?array $params = null
    ) {
        $this->method = $method;
        $this->params = $params;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     method: string,
     *     params?: array<string, mixed>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['method']);

        return new self(
            self::asString($data['method']),
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
        $result = [];

        $result['method'] = $this->method;
        if ($this->params !== null) {
            $result['params'] = $this->params;
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getParams(): ?array
    {
        return $this->params;
    }
}
