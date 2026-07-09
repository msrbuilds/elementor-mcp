<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Core\DTO;

use WP\McpSchema\Common\Protocol\DTO\BaseMetadata;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Identifies a prompt.
 *
 * @since 2024-11-05
 * @last-updated 2025-06-18 (added properties: title)
 *
 * @mcp-domain Server
 * @mcp-subdomain Core
 * @mcp-version 2025-11-25
 */
class PromptReference extends BaseMetadata
{
    use ValidatesRequiredFields;

    public const TYPE = 'ref/prompt';

    /**
     * @since 2024-11-05
     *
     * @var 'ref/prompt'
     */
    protected string $type;

    /**
     * @param string $name @since 2024-11-05
     * @param string|null $title @since 2025-06-18
     */
    public function __construct(
        string $name,
        ?string $title = null
    ) {
        parent::__construct($name, $title);
        $this->type = self::TYPE;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     name: string,
     *     title?: string|null,
     *     type: 'ref/prompt'
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['name']);

        return new self(
            self::asString($data['name']),
            self::asStringOrNull($data['title'] ?? null)
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

        $result['type'] = $this->type;

        return $result;
    }

    /**
     * @return 'ref/prompt'
     */
    public function getType(): string
    {
        return $this->type;
    }
}
