<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Sampling\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Hints to use for model selection.
 *
 * Keys not declared here are currently left unspecified by the spec and are up
 * to the client to interpret.
 *
 * @since 2024-11-05
 *
 * @mcp-domain Client
 * @mcp-subdomain Sampling
 * @mcp-version 2025-11-25
 */
class ModelHint extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * A hint for a model name.
     *
     * The client SHOULD treat this as a substring of a model name; for example:
     * - `claude-3-5-sonnet` should match `claude-3-5-sonnet-20241022`
     * - `sonnet` should match `claude-3-5-sonnet-20241022`, `claude-3-sonnet-20240229`, etc.
     * - `claude` should match any Claude model
     *
     * The client MAY also map the string to a different provider's model name or a different model family, as long as it fills a similar niche; for example:
     * - `gemini-1.5-flash` could match `claude-3-haiku-20240307`
     *
     * @since 2024-11-05
     *
     * @var string|null
     */
    protected ?string $name;

    /**
     * @param string|null $name @since 2024-11-05
     */
    public function __construct(
        ?string $name = null
    ) {
        $this->name = $name;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     name?: string|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::asStringOrNull($data['name'] ?? null)
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

        if ($this->name !== null) {
            $result['name'] = $this->name;
        }

        return $result;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }
}
