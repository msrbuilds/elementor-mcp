<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Lifecycle\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Present if the server offers any resources to read.
 *
 * @mcp-domain Server
 * @mcp-subdomain Lifecycle
 * @mcp-version 2025-11-25
 */
class ServerCapabilitiesResources extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * Whether this server supports subscribing to resource updates.
     *
     * @var bool|null
     */
    protected ?bool $subscribe;

    /**
     * Whether this server supports notifications for changes to the resource list.
     *
     * @var bool|null
     */
    protected ?bool $listChanged;

    /**
     * @param bool|null $subscribe
     * @param bool|null $listChanged
     */
    public function __construct(
        ?bool $subscribe = null,
        ?bool $listChanged = null
    ) {
        $this->subscribe = $subscribe;
        $this->listChanged = $listChanged;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     subscribe?: bool|null,
     *     listChanged?: bool|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::asBoolOrNull($data['subscribe'] ?? null),
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

        if ($this->subscribe !== null) {
            $result['subscribe'] = $this->subscribe;
        }
        if ($this->listChanged !== null) {
            $result['listChanged'] = $this->listChanged;
        }

        return $result;
    }

    /**
     * @return bool|null
     */
    public function getSubscribe(): ?bool
    {
        return $this->subscribe;
    }

    /**
     * @return bool|null
     */
    public function getListChanged(): ?bool
    {
        return $this->listChanged;
    }
}
