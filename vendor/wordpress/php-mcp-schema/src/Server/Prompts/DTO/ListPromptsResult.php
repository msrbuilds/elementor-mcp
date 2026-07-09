<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Prompts\DTO;

use WP\McpSchema\Common\Protocol\DTO\PaginatedResult;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;
use WP\McpSchema\Server\Lifecycle\Union\ServerResultInterface;

/**
 * The server's response to a prompts/list request from the client.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: prompts)
 *
 * @mcp-domain Server
 * @mcp-subdomain Prompts
 * @mcp-version 2025-11-25
 */
class ListPromptsResult extends PaginatedResult implements ServerResultInterface
{
    use ValidatesRequiredFields;

    /**
     * @since 2024-11-05
     *
     * @var array<\WP\McpSchema\Server\Prompts\DTO\Prompt>
     */
    protected array $prompts;

    /**
     * @param array<\WP\McpSchema\Server\Prompts\DTO\Prompt> $prompts @since 2024-11-05
     * @param string|null $nextCursor @since 2024-11-05
     * @param array<string, mixed>|null $_meta @since 2024-11-05
     */
    public function __construct(
        array $prompts,
        ?string $nextCursor = null,
        ?array $_meta = null
    ) {
        parent::__construct($_meta, $nextCursor);
        $this->prompts = $prompts;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     nextCursor?: string|null,
     *     _meta?: array<string, mixed>|null,
     *     prompts: array<array<string, mixed>|\WP\McpSchema\Server\Prompts\DTO\Prompt>
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['prompts']);

        /** @var array<\WP\McpSchema\Server\Prompts\DTO\Prompt> $prompts */
        $prompts = array_map(
            static fn($item) => is_array($item)
                ? Prompt::fromArray($item)
                : $item,
            self::asArray($data['prompts'])
        );

        return new self(
            $prompts,
            self::asStringOrNull($data['nextCursor'] ?? null),
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

        $result['prompts'] = array_map(static fn($item) => $item->toArray(), $this->prompts);

        return $result;
    }

    /**
     * @return array<\WP\McpSchema\Server\Prompts\DTO\Prompt>
     */
    public function getPrompts(): array
    {
        return $this->prompts;
    }
}
