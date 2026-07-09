<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Sampling\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Controls tool selection behavior for sampling requests.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Client
 * @mcp-subdomain Sampling
 * @mcp-version 2025-11-25
 */
class ToolChoice extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * Controls the tool use ability of the model:
     * - "auto": Model decides whether to use tools (default)
     * - "required": Model MUST use at least one tool before completing
     * - "none": Model MUST NOT use any tools
     *
     * @since 2025-11-25
     *
     * @var 'auto'|'required'|'none'|null
     */
    protected ?string $mode;

    /**
     * @param 'auto'|'required'|'none'|null $mode @since 2025-11-25
     */
    public function __construct(
        ?string $mode = null
    ) {
        $this->mode = $mode;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     mode?: 'auto'|'required'|'none'|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        /** @var 'auto'|'required'|'none'|null $mode */
        $mode = isset($data['mode'])
            ? self::asStringOrNull($data['mode'])
            : null;

        return new self(
            $mode
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

        if ($this->mode !== null) {
            $result['mode'] = $this->mode;
        }

        return $result;
    }

    /**
     * @return 'auto'|'required'|'none'|null
     */
    public function getMode(): ?string
    {
        return $this->mode;
    }
}
