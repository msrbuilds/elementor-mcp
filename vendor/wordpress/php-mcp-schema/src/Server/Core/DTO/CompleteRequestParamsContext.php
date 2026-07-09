<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Core\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Additional, optional context for completions
 *
 * @mcp-domain Server
 * @mcp-subdomain Core
 * @mcp-version 2025-11-25
 */
class CompleteRequestParamsContext extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * Previously-resolved variables in a URI template or prompt.
     *
     * @var array<string, string>|null
     */
    protected ?array $arguments;

    /**
     * @param array<string, string>|null $arguments
     */
    public function __construct(
        ?array $arguments = null
    ) {
        $this->arguments = $arguments;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     arguments?: array<string, string>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::asStringMapOrNull($data['arguments'] ?? null)
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

        if ($this->arguments !== null) {
            $result['arguments'] = $this->arguments;
        }

        return $result;
    }

    /**
     * @return array<string, string>|null
     */
    public function getArguments(): ?array
    {
        return $this->arguments;
    }
}
