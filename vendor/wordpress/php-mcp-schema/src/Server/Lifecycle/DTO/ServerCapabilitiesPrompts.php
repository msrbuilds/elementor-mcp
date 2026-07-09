<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Lifecycle\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Present if the server offers any prompt templates.
 *
 * @mcp-domain Server
 * @mcp-subdomain Lifecycle
 * @mcp-version 2025-11-25
 */
class ServerCapabilitiesPrompts extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * Whether this server supports notifications for changes to the prompt list.
     *
     * @var bool|null
     */
    protected ?bool $listChanged;

    /**
     * @param bool|null $listChanged
     */
    public function __construct(
        ?bool $listChanged = null
    ) {
        $this->listChanged = $listChanged;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     listChanged?: bool|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::asBoolOrNull($data['listChanged'] ?? null)
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

        if ($this->listChanged !== null) {
            $result['listChanged'] = $this->listChanged;
        }

        return $result;
    }

    /**
     * @return bool|null
     */
    public function getListChanged(): ?bool
    {
        return $this->listChanged;
    }
}
