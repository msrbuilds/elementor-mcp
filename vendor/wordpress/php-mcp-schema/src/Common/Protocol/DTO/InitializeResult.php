<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Common\Lifecycle\DTO\Implementation;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;
use WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilities;
use WP\McpSchema\Server\Lifecycle\Union\ServerResultInterface;

/**
 * After receiving an initialize request from the client, the server sends this response.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified properties: capabilities, serverInfo)
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class InitializeResult extends Result implements ServerResultInterface
{
    use ValidatesRequiredFields;

    /**
     * The version of the Model Context Protocol that the server wants to use. This may not match the version that the client requested. If the client cannot support this version, it MUST disconnect.
     *
     * @since 2024-11-05
     *
     * @var string
     */
    protected string $protocolVersion;

    /**
     * @since 2024-11-05
     *
     * @var \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilities
     */
    protected ServerCapabilities $capabilities;

    /**
     * @since 2024-11-05
     *
     * @var \WP\McpSchema\Common\Lifecycle\DTO\Implementation
     */
    protected Implementation $serverInfo;

    /**
     * Instructions describing how to use the server and its features.
     *
     * This can be used by clients to improve the LLM's understanding of available tools, resources, etc. It can be thought of like a "hint" to the model. For example, this information MAY be added to the system prompt.
     *
     * @since 2024-11-05
     *
     * @var string|null
     */
    protected ?string $instructions;

    /**
     * @param string $protocolVersion @since 2024-11-05
     * @param \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilities $capabilities @since 2024-11-05
     * @param \WP\McpSchema\Common\Lifecycle\DTO\Implementation $serverInfo @since 2024-11-05
     * @param array<string, mixed>|null $_meta @since 2024-11-05
     * @param string|null $instructions @since 2024-11-05
     */
    public function __construct(
        string $protocolVersion,
        ServerCapabilities $capabilities,
        Implementation $serverInfo,
        ?array $_meta = null,
        ?string $instructions = null
    ) {
        parent::__construct($_meta);
        $this->protocolVersion = $protocolVersion;
        $this->capabilities = $capabilities;
        $this->serverInfo = $serverInfo;
        $this->instructions = $instructions;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|null,
     *     protocolVersion: string,
     *     capabilities: array<string, mixed>|\WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilities,
     *     serverInfo: array<string, mixed>|\WP\McpSchema\Common\Lifecycle\DTO\Implementation,
     *     instructions?: string|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['protocolVersion', 'capabilities', 'serverInfo']);

        /** @var \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilities $capabilities */
        $capabilities = is_array($data['capabilities'])
            ? ServerCapabilities::fromArray(self::asArray($data['capabilities']))
            : $data['capabilities'];

        /** @var \WP\McpSchema\Common\Lifecycle\DTO\Implementation $serverInfo */
        $serverInfo = is_array($data['serverInfo'])
            ? Implementation::fromArray(self::asArray($data['serverInfo']))
            : $data['serverInfo'];

        return new self(
            self::asString($data['protocolVersion']),
            $capabilities,
            $serverInfo,
            self::asArrayOrNull($data['_meta'] ?? null),
            self::asStringOrNull($data['instructions'] ?? null)
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
        $result['serverInfo'] = $this->serverInfo->toArray();
        if ($this->instructions !== null) {
            $result['instructions'] = $this->instructions;
        }

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
     * @return \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilities
     */
    public function getCapabilities(): ServerCapabilities
    {
        return $this->capabilities;
    }

    /**
     * @return \WP\McpSchema\Common\Lifecycle\DTO\Implementation
     */
    public function getServerInfo(): Implementation
    {
        return $this->serverInfo;
    }

    /**
     * @return string|null
     */
    public function getInstructions(): ?string
    {
        return $this->instructions;
    }
}
