<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Lifecycle\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Present if the client supports task-augmented requests.
 *
 * @mcp-domain Client
 * @mcp-subdomain Lifecycle
 * @mcp-version 2025-11-25
 */
class ClientCapabilitiesTasks extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * Whether this client supports tasks/list.
     *
     * @var object|null
     */
    protected ?object $list;

    /**
     * Whether this client supports tasks/cancel.
     *
     * @var object|null
     */
    protected ?object $cancel;

    /**
     * @param object|null $list
     * @param object|null $cancel
     */
    public function __construct(
        ?object $list = null,
        ?object $cancel = null
    ) {
        $this->list = $list;
        $this->cancel = $cancel;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     list?: object|null,
     *     cancel?: object|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::asObjectOrNull($data['list'] ?? null),
            self::asObjectOrNull($data['cancel'] ?? null)
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

        if ($this->list !== null) {
            $result['list'] = $this->list;
        }
        if ($this->cancel !== null) {
            $result['cancel'] = $this->cancel;
        }

        return $result;
    }

    /**
     * @return object|null
     */
    public function getList(): ?object
    {
        return $this->list;
    }

    /**
     * @return object|null
     */
    public function getCancel(): ?object
    {
        return $this->cancel;
    }
}
