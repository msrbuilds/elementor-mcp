<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\DTO;

use WP\McpSchema\Client\Elicitation\Union\PrimitiveSchemaDefinitionInterface;
use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * @since 2025-06-18
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
class BooleanSchema extends AbstractDataTransferObject implements PrimitiveSchemaDefinitionInterface
{
    use ValidatesRequiredFields;

    public const TYPE = 'boolean';

    public const DISCRIMINATOR_FIELD = 'type';
    public const DISCRIMINATOR_VALUE = 'boolean';

    /**
     * @since 2025-06-18
     *
     * @var 'boolean'
     */
    protected string $type;

    /**
     * @since 2025-06-18
     *
     * @var string|null
     */
    protected ?string $title;

    /**
     * @since 2025-06-18
     *
     * @var string|null
     */
    protected ?string $description;

    /**
     * @since 2025-06-18
     *
     * @var bool|null
     */
    protected ?bool $default;

    /**
     * @param string|null $title @since 2025-06-18
     * @param string|null $description @since 2025-06-18
     * @param bool|null $default @since 2025-06-18
     */
    public function __construct(
        ?string $title = null,
        ?string $description = null,
        ?bool $default = null
    ) {
        $this->type = self::TYPE;
        $this->title = $title;
        $this->description = $description;
        $this->default = $default;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     type: 'boolean',
     *     title?: string|null,
     *     description?: string|null,
     *     default?: bool|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::asStringOrNull($data['title'] ?? null),
            self::asStringOrNull($data['description'] ?? null),
            self::asBoolOrNull($data['default'] ?? null)
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

        $result['type'] = $this->type;
        if ($this->title !== null) {
            $result['title'] = $this->title;
        }
        if ($this->description !== null) {
            $result['description'] = $this->description;
        }
        if ($this->default !== null) {
            $result['default'] = $this->default;
        }

        return $result;
    }

    /**
     * @return 'boolean'
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
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
    public function getDefault(): ?bool
    {
        return $this->default;
    }
}
