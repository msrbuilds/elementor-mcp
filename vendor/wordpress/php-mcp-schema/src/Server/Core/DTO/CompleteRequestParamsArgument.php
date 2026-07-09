<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Core\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * The argument's information
 *
 * @mcp-domain Server
 * @mcp-subdomain Core
 * @mcp-version 2025-11-25
 */
class CompleteRequestParamsArgument extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * The name of the argument
     *
     * @var string
     */
    protected string $name;

    /**
     * The value of the argument to use for completion matching.
     *
     * @var string
     */
    protected string $value;

    /**
     * @param string $name
     * @param string $value
     */
    public function __construct(
        string $name,
        string $value
    ) {
        $this->name = $name;
        $this->value = $value;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     name: string,
     *     value: string
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['name', 'value']);

        return new self(
            self::asString($data['name']),
            self::asString($data['value'])
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

        $result['name'] = $this->name;
        $result['value'] = $this->value;

        return $result;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }
}
