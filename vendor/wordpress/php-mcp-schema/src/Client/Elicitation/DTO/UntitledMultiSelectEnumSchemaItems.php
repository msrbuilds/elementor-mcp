<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Schema for the array items.
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
class UntitledMultiSelectEnumSchemaItems extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    public const TYPE = 'string';

    /**
     * @var 'string'
     */
    protected string $type;

    /**
     * Array of enum values to choose from.
     *
     * @var array<string>
     */
    protected array $enum;

    /**
     * @param array<string> $enum
     */
    public function __construct(
        array $enum
    ) {
        $this->type = self::TYPE;
        $this->enum = $enum;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     type: 'string',
     *     enum: array<string>
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['enum']);

        return new self(
            self::asStringArray($data['enum'])
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
        $result['enum'] = $this->enum;

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
     * @return array<string>
     */
    public function getEnum(): array
    {
        return $this->enum;
    }
}
