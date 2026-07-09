<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\DTO;

use WP\McpSchema\Client\Elicitation\Union\MultiSelectEnumSchemaInterface;
use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Schema for multiple-selection enumeration with display titles for each option.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
class TitledMultiSelectEnumSchema extends AbstractDataTransferObject implements MultiSelectEnumSchemaInterface
{
    use ValidatesRequiredFields;

    public const TYPE = 'array';

    public const DISCRIMINATOR_FIELD = 'type';
    public const DISCRIMINATOR_VALUE = 'array';

    /**
     * @since 2025-11-25
     *
     * @var 'array'
     */
    protected string $type;

    /**
     * Optional title for the enum field.
     *
     * @since 2025-11-25
     *
     * @var string|null
     */
    protected ?string $title;

    /**
     * Optional description for the enum field.
     *
     * @since 2025-11-25
     *
     * @var string|null
     */
    protected ?string $description;

    /**
     * Minimum number of items to select.
     *
     * @since 2025-11-25
     *
     * @var int|null
     */
    protected ?int $minItems;

    /**
     * Maximum number of items to select.
     *
     * @since 2025-11-25
     *
     * @var int|null
     */
    protected ?int $maxItems;

    /**
     * Schema for array items with enum options and display labels.
     *
     * @since 2025-11-25
     *
     * @var \WP\McpSchema\Client\Elicitation\DTO\TitledMultiSelectEnumSchemaItems
     */
    protected TitledMultiSelectEnumSchemaItems $items;

    /**
     * Optional default value.
     *
     * @since 2025-11-25
     *
     * @var array<string>|null
     */
    protected ?array $default;

    /**
     * @param \WP\McpSchema\Client\Elicitation\DTO\TitledMultiSelectEnumSchemaItems $items @since 2025-11-25
     * @param string|null $title @since 2025-11-25
     * @param string|null $description @since 2025-11-25
     * @param int|null $minItems @since 2025-11-25
     * @param int|null $maxItems @since 2025-11-25
     * @param array<string>|null $default @since 2025-11-25
     */
    public function __construct(
        TitledMultiSelectEnumSchemaItems $items,
        ?string $title = null,
        ?string $description = null,
        ?int $minItems = null,
        ?int $maxItems = null,
        ?array $default = null
    ) {
        $this->type = self::TYPE;
        $this->items = $items;
        $this->title = $title;
        $this->description = $description;
        $this->minItems = $minItems;
        $this->maxItems = $maxItems;
        $this->default = $default;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     type: 'array',
     *     title?: string|null,
     *     description?: string|null,
     *     minItems?: int|null,
     *     maxItems?: int|null,
     *     items: array<string, mixed>|\WP\McpSchema\Client\Elicitation\DTO\TitledMultiSelectEnumSchemaItems,
     *     default?: array<string>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['items']);

        /** @var \WP\McpSchema\Client\Elicitation\DTO\TitledMultiSelectEnumSchemaItems $items */
        $items = is_array($data['items'])
            ? TitledMultiSelectEnumSchemaItems::fromArray(self::asArray($data['items']))
            : $data['items'];

        return new self(
            $items,
            self::asStringOrNull($data['title'] ?? null),
            self::asStringOrNull($data['description'] ?? null),
            self::asIntOrNull($data['minItems'] ?? null),
            self::asIntOrNull($data['maxItems'] ?? null),
            self::asStringArrayOrNull($data['default'] ?? null)
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
        if ($this->minItems !== null) {
            $result['minItems'] = $this->minItems;
        }
        if ($this->maxItems !== null) {
            $result['maxItems'] = $this->maxItems;
        }
        $result['items'] = $this->items->toArray();
        if ($this->default !== null) {
            $result['default'] = $this->default;
        }

        return $result;
    }

    /**
     * @return 'array'
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
     * @return int|null
     */
    public function getMinItems(): ?int
    {
        return $this->minItems;
    }

    /**
     * @return int|null
     */
    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    /**
     * @return \WP\McpSchema\Client\Elicitation\DTO\TitledMultiSelectEnumSchemaItems
     */
    public function getItems(): TitledMultiSelectEnumSchemaItems
    {
        return $this->items;
    }

    /**
     * @return array<string>|null
     */
    public function getDefault(): ?array
    {
        return $this->default;
    }
}
