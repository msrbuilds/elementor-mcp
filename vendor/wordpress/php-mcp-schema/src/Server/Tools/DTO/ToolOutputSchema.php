<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Tools\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * An optional JSON Schema object defining the structure of the tool's output returned in
 * the structuredContent field of a CallToolResult.
 *
 * Defaults to JSON Schema 2020-12 when no explicit $schema is provided.
 * Currently restricted to type: "object" at the root level.
 *
 * @mcp-domain Server
 * @mcp-subdomain Tools
 * @mcp-version 2025-11-25
 */
class ToolOutputSchema extends AbstractDataTransferObject
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
     * @var array<string, object>|null
     */
    protected ?array $properties;

    /**
     * @var array<string>|null
     */
    protected ?array $required;

    /**
     * @param string|null $schema
     * @param array<string, object>|null $properties
     * @param array<string>|null $required
     */
    public function __construct(
        ?string $schema = null,
        ?array $properties = null,
        ?array $required = null
    ) {
        $this->type = self::TYPE;
        $this->schema = $schema;
        $this->properties = $properties;
        $this->required = $required;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     '$schema'?: string|null,
     *     type: 'object',
     *     properties?: array<string, object>|null,
     *     required?: array<string>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::asStringOrNull($data['$schema'] ?? null),
            self::asObjectMapOrNull($data['properties'] ?? null),
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
        $result['properties'] = !empty($this->properties)
            ? $this->properties
            : new \stdClass();
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
     * @return array<string, object>|null
     */
    public function getProperties(): ?array
    {
        return $this->properties;
    }

    /**
     * @return array<string>|null
     */
    public function getRequired(): ?array
    {
        return $this->required;
    }
}
