<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\DTO;

use WP\McpSchema\Client\Elicitation\Union\PrimitiveSchemaDefinitionInterface;
use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * @since 2025-06-18
 * @last-updated 2025-11-25 (added properties: default)
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
class StringSchema extends AbstractDataTransferObject implements PrimitiveSchemaDefinitionInterface
{
    use ValidatesRequiredFields;

    public const TYPE = 'string';

    public const DISCRIMINATOR_FIELD = 'type';
    public const DISCRIMINATOR_VALUE = 'string';

    /**
     * @since 2025-06-18
     *
     * @var 'string'
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
     * @var int|null
     */
    protected ?int $minLength;

    /**
     * @since 2025-06-18
     *
     * @var int|null
     */
    protected ?int $maxLength;

    /**
     * @since 2025-06-18
     *
     * @var 'email'|'uri'|'date'|'date-time'|null
     */
    protected ?string $format;

    /**
     * @since 2025-11-25
     *
     * @var string|null
     */
    protected ?string $default;

    /**
     * @param string|null $title @since 2025-06-18
     * @param string|null $description @since 2025-06-18
     * @param int|null $minLength @since 2025-06-18
     * @param int|null $maxLength @since 2025-06-18
     * @param 'email'|'uri'|'date'|'date-time'|null $format @since 2025-06-18
     * @param string|null $default @since 2025-11-25
     */
    public function __construct(
        ?string $title = null,
        ?string $description = null,
        ?int $minLength = null,
        ?int $maxLength = null,
        ?string $format = null,
        ?string $default = null
    ) {
        $this->type = self::TYPE;
        $this->title = $title;
        $this->description = $description;
        $this->minLength = $minLength;
        $this->maxLength = $maxLength;
        $this->format = $format;
        $this->default = $default;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     type: 'string',
     *     title?: string|null,
     *     description?: string|null,
     *     minLength?: int|null,
     *     maxLength?: int|null,
     *     format?: 'email'|'uri'|'date'|'date-time'|null,
     *     default?: string|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        /** @var 'email'|'uri'|'date'|'date-time'|null $format */
        $format = isset($data['format'])
            ? self::asStringOrNull($data['format'])
            : null;

        return new self(
            self::asStringOrNull($data['title'] ?? null),
            self::asStringOrNull($data['description'] ?? null),
            self::asIntOrNull($data['minLength'] ?? null),
            self::asIntOrNull($data['maxLength'] ?? null),
            $format,
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
        if ($this->minLength !== null) {
            $result['minLength'] = $this->minLength;
        }
        if ($this->maxLength !== null) {
            $result['maxLength'] = $this->maxLength;
        }
        if ($this->format !== null) {
            $result['format'] = $this->format;
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
     * @return int|null
     */
    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    /**
     * @return int|null
     */
    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    /**
     * @return 'email'|'uri'|'date'|'date-time'|null
     */
    public function getFormat(): ?string
    {
        return $this->format;
    }

    /**
     * @return string|null
     */
    public function getDefault(): ?string
    {
        return $this->default;
    }
}
