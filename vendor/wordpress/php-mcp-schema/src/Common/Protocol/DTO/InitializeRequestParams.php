<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilities;
use WP\McpSchema\Common\JsonRpc\DTO\RequestParams;
use WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta;
use WP\McpSchema\Common\Lifecycle\DTO\Implementation;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Parameters for an `initialize` request.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class InitializeRequestParams extends RequestParams
{
    use ValidatesRequiredFields;

    /**
     * The latest version of the Model Context Protocol that the client supports. The client MAY decide to support older versions as well.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $protocolVersion;

    /**
     * @since 2025-11-25
     *
     * @var \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilities
     */
    protected ClientCapabilities $capabilities;

    /**
     * @since 2025-11-25
     *
     * @var \WP\McpSchema\Common\Lifecycle\DTO\Implementation
     */
    protected Implementation $clientInfo;

    /**
     * @param string $protocolVersion @since 2025-11-25
     * @param \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilities $capabilities @since 2025-11-25
     * @param \WP\McpSchema\Common\Lifecycle\DTO\Implementation $clientInfo @since 2025-11-25
     * @param \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta @since 2025-11-25
     */
    public function __construct(
        string $protocolVersion,
        ClientCapabilities $capabilities,
        Implementation $clientInfo,
        ?RequestParamsMeta $_meta = null
    ) {
        parent::__construct($_meta);
        $this->protocolVersion = $protocolVersion;
        $this->capabilities = $capabilities;
        $this->clientInfo = $clientInfo;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|\WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null,
     *     protocolVersion: string,
     *     capabilities: array<string, mixed>|\WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilities,
     *     clientInfo: array<string, mixed>|\WP\McpSchema\Common\Lifecycle\DTO\Implementation
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['protocolVersion', 'capabilities', 'clientInfo']);

        /** @var \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilities $capabilities */
        $capabilities = is_array($data['capabilities'])
            ? ClientCapabilities::fromArray(self::asArray($data['capabilities']))
            : $data['capabilities'];

        /** @var \WP\McpSchema\Common\Lifecycle\DTO\Implementation $clientInfo */
        $clientInfo = is_array($data['clientInfo'])
            ? Implementation::fromArray(self::asArray($data['clientInfo']))
            : $data['clientInfo'];

        /** @var \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta */
        $_meta = isset($data['_meta'])
            ? (is_array($data['_meta'])
                ? RequestParamsMeta::fromArray(self::asArray($data['_meta']))
                : $data['_meta'])
            : null;

        return new self(
            self::asString($data['protocolVersion']),
            $capabilities,
            $clientInfo,
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

        $result['protocolVersion'] = $this->protocolVersion;
        $result['capabilities'] = $this->capabilities->toArray();
        $result['clientInfo'] = $this->clientInfo->toArray();

        return $result;
    }

    /**
     * @return string
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * @return \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilities
     */
    public function getCapabilities(): ClientCapabilities
    {
        return $this->capabilities;
    }

    /**
     * @return \WP\McpSchema\Common\Lifecycle\DTO\Implementation
     */
    public function getClientInfo(): Implementation
    {
        return $this->clientInfo;
    }
}
