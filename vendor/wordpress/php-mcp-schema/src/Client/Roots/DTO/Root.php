<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Roots\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Represents a root directory or file that the server can operate on.
 *
 * @since 2024-11-05
 * @last-updated 2025-06-18 (added properties: _meta)
 *
 * @mcp-domain Client
 * @mcp-subdomain Roots
 * @mcp-version 2025-11-25
 */
class Root extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * The URI identifying the root. This *must* start with file:// for now.
     * This restriction may be relaxed in future versions of the protocol to allow
     * other URI schemes.
     *
     * @since 2024-11-05
     *
     * @var string
     */
    protected string $uri;

    /**
     * An optional name for the root. This can be used to provide a human-readable
     * identifier for the root, which may be useful for display purposes or for
     * referencing the root in other parts of the application.
     *
     * @since 2024-11-05
     *
     * @var string|null
     */
    protected ?string $name;

    /**
     * See [General fields: `_meta`](/specification/2025-11-25/basic/index#meta) for notes on `_meta` usage.
     *
     * @since 2025-06-18
     *
     * @var array<string, mixed>|null
     */
    protected ?array $_meta;

    /**
     * @param string $uri @since 2024-11-05
     * @param string|null $name @since 2024-11-05
     * @param array<string, mixed>|null $_meta @since 2025-06-18
     */
    public function __construct(
        string $uri,
        ?string $name = null,
        ?array $_meta = null
    ) {
        $this->uri = $uri;
        $this->name = $name;
        $this->_meta = $_meta;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     uri: string,
     *     name?: string|null,
     *     _meta?: array<string, mixed>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['uri']);

        return new self(
            self::asString($data['uri']),
            self::asStringOrNull($data['name'] ?? null),
            self::asArrayOrNull($data['_meta'] ?? null)
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

        $result['uri'] = $this->uri;
        if ($this->name !== null) {
            $result['name'] = $this->name;
        }
        if ($this->_meta !== null) {
            $result['_meta'] = $this->_meta;
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_meta(): ?array
    {
        return $this->_meta;
    }
}
