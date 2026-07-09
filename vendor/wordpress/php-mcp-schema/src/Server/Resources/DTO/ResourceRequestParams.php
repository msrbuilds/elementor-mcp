<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Resources\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\RequestParams;
use WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Common parameters when working with resources.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Server
 * @mcp-subdomain Resources
 * @mcp-version 2025-11-25
 */
class ResourceRequestParams extends RequestParams
{
    use ValidatesRequiredFields;

    /**
     * The URI of the resource. The URI can use any protocol; it is up to the server how to interpret it.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $uri;

    /**
     * @param string $uri @since 2025-11-25
     * @param \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta @since 2025-11-25
     */
    public function __construct(
        string $uri,
        ?RequestParamsMeta $_meta = null
    ) {
        parent::__construct($_meta);
        $this->uri = $uri;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|\WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null,
     *     uri: string
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['uri']);

        /** @var \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta */
        $_meta = isset($data['_meta'])
            ? (is_array($data['_meta'])
                ? RequestParamsMeta::fromArray(self::asArray($data['_meta']))
                : $data['_meta'])
            : null;

        return new self(
            self::asString($data['uri']),
            $_meta
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

        $result['uri'] = $this->uri;

        return $result;
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }
}
