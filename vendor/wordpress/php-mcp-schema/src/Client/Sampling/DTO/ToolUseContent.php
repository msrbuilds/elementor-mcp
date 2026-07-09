<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Sampling\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * A request from the assistant to call a tool.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Client
 * @mcp-subdomain Sampling
 * @mcp-version 2025-11-25
 */
class ToolUseContent extends AbstractDataTransferObject implements SamplingMessageContentBlockInterface
{
    use ValidatesRequiredFields;

    public const TYPE = 'tool_use';

    public const DISCRIMINATOR_FIELD = 'type';
    public const DISCRIMINATOR_VALUE = 'tool_use';

    /**
     * @since 2025-11-25
     *
     * @var 'tool_use'
     */
    protected string $type;

    /**
     * A unique identifier for this tool use.
     *
     * This ID is used to match tool results to their corresponding tool uses.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $id;

    /**
     * The name of the tool to call.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $name;

    /**
     * The arguments to pass to the tool, conforming to the tool's input schema.
     *
     * @since 2025-11-25
     *
     * @var array<string, mixed>
     */
    protected array $input;

    /**
     * Optional metadata about the tool use. Clients SHOULD preserve this field when
     * including tool uses in subsequent sampling requests to enable caching optimizations.
     *
     * See [General fields: `_meta`](/specification/2025-11-25/basic/index#meta) for notes on `_meta` usage.
     *
     * @since 2025-11-25
     *
     * @var array<string, mixed>|null
     */
    protected ?array $_meta;

    /**
     * @param string $id @since 2025-11-25
     * @param string $name @since 2025-11-25
     * @param array<string, mixed> $input @since 2025-11-25
     * @param array<string, mixed>|null $_meta @since 2025-11-25
     */
    public function __construct(
        string $id,
        string $name,
        array $input,
        ?array $_meta = null
    ) {
        $this->type = self::TYPE;
        $this->id = $id;
        $this->name = $name;
        $this->input = $input;
        $this->_meta = $_meta;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     type: 'tool_use',
     *     id: string,
     *     name: string,
     *     input: array<string, mixed>,
     *     _meta?: array<string, mixed>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['id', 'name', 'input']);

        return new self(
            self::asString($data['id']),
            self::asString($data['name']),
            self::asArray($data['input']),
            self::asArrayOrNull($data['_meta'] ?? null)
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
        $result['id'] = $this->id;
        $result['name'] = $this->name;
        $result['input'] = $this->input;
        if ($this->_meta !== null) {
            $result['_meta'] = $this->_meta;
        }

        return $result;
    }

    /**
     * @return 'tool_use'
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInput(): array
    {
        return $this->input;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_meta(): ?array
    {
        return $this->_meta;
    }
}
