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
class NumberSchema extends AbstractDataTransferObject implements PrimitiveSchemaDefinitionInterface
{
    use ValidatesRequiredFields;

    /**
     * @since 2025-06-18
     *
     * @var 'number'|'integer'
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
     * @var float|null
     */
    protected ?float $minimum;

    /**
     * @since 2025-06-18
     *
     * @var float|null
     */
    protected ?float $maximum;

    /**
     * @since 2025-11-25
     *
     * @var float|null
     */
    protected ?float $default;

    /**
     * @param 'number'|'integer' $type @since 2025-06-18
     * @param string|null $title @since 2025-06-18
     * @param string|null $description @since 2025-06-18
     * @param float|null $minimum @since 2025-06-18
     * @param float|null $maximum @since 2025-06-18
     * @param float|null $default @since 2025-11-25
     */
    public function __construct(
        string $type,
        ?string $title = null,
        ?string $description = null,
        ?float $minimum = null,
        ?float $maximum = null,
        ?float $default = null
    ) {
        $this->type = $type;
        $this->title = $title;
        $this->description = $description;
        $this->minimum = $minimum;
        $this->maximum = $maximum;
        $this->default = $default;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     type: 'number'|'integer',
     *     title?: string|null,
     *     description?: string|null,
     *     minimum?: float|null,
     *     maximum?: float|null,
     *     default?: float|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['type']);

        /** @var 'number'|'integer' $type */
        $type = self::asString($data['type']);

        return new self(
            $type,
            self::asStringOrNull($data['title'] ?? null),
            self::asStringOrNull($data['description'] ?? null),
            self::asFloatOrNull($data['minimum'] ?? null),
            self::asFloatOrNull($data['maximum'] ?? null),
            self::asFloatOrNull($data['default'] ?? null)
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
        if ($this->minimum !== null) {
            $result['minimum'] = $this->minimum;
        }
        if ($this->maximum !== null) {
            $result['maximum'] = $this->maximum;
        }
        if ($this->default !== null) {
            $result['default'] = $this->default;
        }

        return $result;
    }

    /**
     * @return 'number'|'integer'
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
     * @return float|null
     */
    public function getMinimum(): ?float
    {
        return $this->minimum;
    }

    /**
     * @return float|null
     */
    public function getMaximum(): ?float
    {
        return $this->maximum;
    }

    /**
     * @return float|null
     */
    public function getDefault(): ?float
    {
        return $this->default;
    }
}
