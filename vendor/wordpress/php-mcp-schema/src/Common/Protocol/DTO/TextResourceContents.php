<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Common\Traits\ValidatesRequiredFields;
use WP\McpSchema\Server\Resources\DTO\ResourceContents;

/**
 * @since 2024-11-05
 * @last-updated 2025-06-18 (added properties: _meta)
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class TextResourceContents extends ResourceContents
{
    use ValidatesRequiredFields;

    /**
     * The text of the item. This must only be set if the item can actually be represented as text (not binary data).
     *
     * @since 2024-11-05
     *
     * @var string
     */
    protected string $text;

    /**
     * @param string $uri @since 2024-11-05
     * @param string $text @since 2024-11-05
     * @param string|null $mimeType @since 2024-11-05
     * @param array<string, mixed>|null $_meta @since 2025-06-18
     */
    public function __construct(
        string $uri,
        string $text,
        ?string $mimeType = null,
        ?array $_meta = null
    ) {
        parent::__construct($uri, $mimeType, $_meta);
        $this->text = $text;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     uri: string,
     *     mimeType?: string|null,
     *     _meta?: array<string, mixed>|null,
     *     text: string
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['uri', 'text']);

        return new self(
            self::asString($data['uri']),
            self::asString($data['text']),
            self::asStringOrNull($data['mimeType'] ?? null),
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

        $result['text'] = $this->text;

        return $result;
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return $this->text;
    }
}
