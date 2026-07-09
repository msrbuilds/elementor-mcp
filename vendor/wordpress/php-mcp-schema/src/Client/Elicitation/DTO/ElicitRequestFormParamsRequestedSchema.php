<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * A restricted subset of JSON Schema.
 * Only top-level properties are allowed, without nesting.
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
class ElicitRequestFormParamsRequestedSchema extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    public const TYPE = 'object';

    /**
     * @var string|null
     */
    protected ?string $schema;

    /**
     * @var 'object'
     */
    protected string $type;

    /**
     * @var array<string>|null
     */
    protected ?array $required;

    /**
     * @param string|null $schema
     * @param array<string>|null $required
     */
    public function __construct(
        ?string $schema = null,
        ?array $required = null
    ) {
        $this->type = self::TYPE;
        $this->schema = $schema;
        $this->required = $required;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     '$schema'?: string|null,
     *     type: 'object',
     *     required?: array<string>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::asStringOrNull($data['$schema'] ?? null),
            self::asStringArrayOrNull($data['required'] ?? null)
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

        if ($this->schema !== null) {
            $result['$schema'] = $this->schema;
        }
        $result['type'] = $this->type;
        if ($this->required !== null) {
            $result['required'] = $this->required;
        }

        return $result;
    }

    /**
     * @return string|null
     */
    public function getSchema(): ?string
    {
        return $this->schema;
    }

    /**
     * @return 'object'
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string>|null
     */
    public function getRequired(): ?array
    {
        return $this->required;
    }
}
