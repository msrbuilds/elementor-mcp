<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\DTO;

use WP\McpSchema\Client\Elicitation\Union\SingleSelectEnumSchemaInterface;
use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Schema for single-selection enumeration without display titles for options.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
class UntitledSingleSelectEnumSchema extends AbstractDataTransferObject implements SingleSelectEnumSchemaInterface
{
    use ValidatesRequiredFields;

    public const TYPE = 'string';

    public const DISCRIMINATOR_FIELD = 'type';
    public const DISCRIMINATOR_VALUE = 'string';

    /**
     * @since 2025-11-25
     *
     * @var 'string'
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
     * Array of enum values to choose from.
     *
     * @since 2025-11-25
     *
     * @var array<string>
     */
    protected array $enum;

    /**
     * Optional default value.
     *
     * @since 2025-11-25
     *
     * @var string|null
     */
    protected ?string $default;

    /**
     * @param array<string> $enum @since 2025-11-25
     * @param string|null $title @since 2025-11-25
     * @param string|null $description @since 2025-11-25
     * @param string|null $default @since 2025-11-25
     */
    public function __construct(
        array $enum,
        ?string $title = null,
        ?string $description = null,
        ?string $default = null
    ) {
        $this->type = self::TYPE;
        $this->enum = $enum;
        $this->title = $title;
        $this->description = $description;
        $this->default = $default;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     type: 'string',
     *     title?: string|null,
     *     description?: string|null,
     *     enum: array<string>,
     *     default?: string|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['enum']);

        return new self(
            self::asStringArray($data['enum']),
            self::asStringOrNull($data['title'] ?? null),
            self::asStringOrNull($data['description'] ?? null),
            self::asStringOrNull($data['default'] ?? null)
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
        $result['enum'] = $this->enum;
        if ($this->default !== null) {
            $result['default'] = $this->default;
        }

        return $result;
    }

    /**
     * @return 'string'
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
     * @return array<string>
     */
    public function getEnum(): array
    {
        return $this->enum;
    }

    /**
     * @return string|null
     */
    public function getDefault(): ?string
    {
        return $this->default;
    }
}
