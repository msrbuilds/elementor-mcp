<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Prompts\DTO;

use WP\McpSchema\Common\Protocol\DTO\BaseMetadata;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Describes an argument that a prompt can accept.
 *
 * @since 2024-11-05
 * @last-updated 2025-06-18 (added properties: title)
 *
 * @mcp-domain Server
 * @mcp-subdomain Prompts
 * @mcp-version 2025-11-25
 */
class PromptArgument extends BaseMetadata
{
    use ValidatesRequiredFields;

    /**
     * A human-readable description of the argument.
     *
     * @since 2024-11-05
     *
     * @var string|null
     */
    protected ?string $description;

    /**
     * Whether this argument must be provided.
     *
     * @since 2024-11-05
     *
     * @var bool|null
     */
    protected ?bool $required;

    /**
     * @param string $name @since 2024-11-05
     * @param string|null $title @since 2025-06-18
     * @param string|null $description @since 2024-11-05
     * @param bool|null $required @since 2024-11-05
     */
    public function __construct(
        string $name,
        ?string $title = null,
        ?string $description = null,
        ?bool $required = null
    ) {
        parent::__construct($name, $title);
        $this->description = $description;
        $this->required = $required;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     name: string,
     *     title?: string|null,
     *     description?: string|null,
     *     required?: bool|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['name']);

        return new self(
            self::asString($data['name']),
            self::asStringOrNull($data['title'] ?? null),
            self::asStringOrNull($data['description'] ?? null),
            self::asBoolOrNull($data['required'] ?? null)
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

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }
        if ($this->required !== null) {
            $result['required'] = $this->required;
        }

        return $result;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return bool|null
     */
    public function getRequired(): ?bool
    {
        return $this->required;
    }
}
