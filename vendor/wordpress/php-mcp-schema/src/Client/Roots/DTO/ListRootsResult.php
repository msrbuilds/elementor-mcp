<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Roots\DTO;

use WP\McpSchema\Client\Lifecycle\Union\ClientResultInterface;
use WP\McpSchema\Common\Protocol\DTO\Result;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * The client's response to a roots/list request from the server.
 * This result contains an array of Root objects, each representing a root directory
 * or file that the server can operate on.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: roots)
 *
 * @mcp-domain Client
 * @mcp-subdomain Roots
 * @mcp-version 2025-11-25
 */
class ListRootsResult extends Result implements ClientResultInterface
{
    use ValidatesRequiredFields;

    /**
     * @since 2024-11-05
     *
     * @var array<\WP\McpSchema\Client\Roots\DTO\Root>
     */
    protected array $roots;

    /**
     * @param array<\WP\McpSchema\Client\Roots\DTO\Root> $roots @since 2024-11-05
     * @param array<string, mixed>|null $_meta @since 2024-11-05
     */
    public function __construct(
        array $roots,
        ?array $_meta = null
    ) {
        parent::__construct($_meta);
        $this->roots = $roots;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|null,
     *     roots: array<array<string, mixed>|\WP\McpSchema\Client\Roots\DTO\Root>
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['roots']);

        /** @var array<\WP\McpSchema\Client\Roots\DTO\Root> $roots */
        $roots = array_map(
            static fn($item) => is_array($item)
                ? Root::fromArray($item)
                : $item,
            self::asArray($data['roots'])
        );

        return new self(
            $roots,
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
        $result = parent::toArray();

        $result['roots'] = array_map(static fn($item) => $item->toArray(), $this->roots);

        return $result;
    }

    /**
     * @return array<\WP\McpSchema\Client\Roots\DTO\Root>
     */
    public function getRoots(): array
    {
        return $this->roots;
    }
}
