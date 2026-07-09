<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\DTO;

use WP\McpSchema\Client\Elicitation\Union\EnumSchemaInterface;
use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Use TitledSingleSelectEnumSchema instead.
 * This interface will be removed in a future version.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
class LegacyTitledEnumSchema extends AbstractDataTransferObject implements EnumSchemaInterface
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
     * @since 2025-11-25
     *
     * @var string|null
     */
    protected ?string $title;

    /**
     * @since 2025-11-25
     *
     * @var string|null
     */
    protected ?string $description;

    /**
     * @since 2025-11-25
     *
     * @var array<string>
     */
    protected array $enum;

    /**
     * (Legacy) Display names for enum values.
     * Non-standard according to JSON schema 2020-12.
     *
     * @since 2025-11-25
     *
     * @var array<string>|null
     */
    protected ?array $enumNames;

    /**
     * @since 2025-11-25
     *
     * @var string|null
     */
    protected ?string $default;

    /**
     * @param array<string> $enum @since 2025-11-25
     * @param string|null $title @since 2025-11-25
     * @param string|null $description @since 2025-11-25
     * @param array<string>|null $enumNames @since 2025-11-25
     * @param string|null $default @since 2025-11-25
     */
    public function __construct(
        array $enum,
        ?string $title = null,
        ?string $description = null,
        ?array $enumNames = null,
        ?string $default = null
    ) {
        $this->type = self::TYPE;
        $this->enum = $enum;
        $this->title = $title;
        $this->description = $description;
        $this->enumNames = $enumNames;
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
     *     enumNames?: array<string>|null,
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
            self::asStringArrayOrNull($data['enumNames'] ?? null),
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
        if ($this->enumNames !== null) {
            $result['enumNames'] = $this->enumNames;
        }
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
     * @return array<string>|null
     */
    public function getEnumNames(): ?array
    {
        return $this->enumNames;
    }

    /**
     * @return string|null
     */
    public function getDefault(): ?string
    {
        return $this->default;
    }
}
