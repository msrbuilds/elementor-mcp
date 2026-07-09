<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Core\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * @mcp-domain Server
 * @mcp-subdomain Core
 * @mcp-version 2025-11-25
 */
class CompleteResultCompletion extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /** Maximum number of items allowed in values per MCP spec */
    public const MAX_VALUES = 100;

    /**
     * An array of completion values. Must not exceed 100 items.
     *
     * @var array<string>
     */
    protected array $values;

    /**
     * The total number of completion options available. This can exceed the number of values actually sent in the response.
     *
     * @var int|null
     */
    protected ?int $total;

    /**
     * Indicates whether there are additional completion options beyond those provided in the current response, even if the exact total is unknown.
     *
     * @var bool|null
     */
    protected ?bool $hasMore;

    /**
     * @param array<string> $values
     * @param int|null $total
     * @param bool|null $hasMore
     */
    public function __construct(
        array $values,
        ?int $total = null,
        ?bool $hasMore = null
    ) {
        $this->values = $values;
        $this->total = $total;
        $this->hasMore = $hasMore;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     values: array<string>,
     *     total?: int|null,
     *     hasMore?: bool|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['values']);

        if (is_array($data['values']) && count($data['values']) > self::MAX_VALUES) {
            throw new \InvalidArgumentException(sprintf(
                '%s::values must not exceed %d items, got %d',
                static::class,
                self::MAX_VALUES,
                count($data['values'])
            ));
        }

        return new self(
            self::asStringArray($data['values']),
            self::asIntOrNull($data['total'] ?? null),
            self::asBoolOrNull($data['hasMore'] ?? null)
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

        $result['values'] = $this->values;
        if ($this->total !== null) {
            $result['total'] = $this->total;
        }
        if ($this->hasMore !== null) {
            $result['hasMore'] = $this->hasMore;
        }

        return $result;
    }

    /**
     * @return array<string>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @return int|null
     */
    public function getTotal(): ?int
    {
        return $this->total;
    }

    /**
     * @return bool|null
     */
    public function getHasMore(): ?bool
    {
        return $this->hasMore;
    }
}
