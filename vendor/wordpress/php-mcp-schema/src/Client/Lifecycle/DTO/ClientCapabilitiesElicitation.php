<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Lifecycle\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Present if the client supports elicitation from the server.
 *
 * @mcp-domain Client
 * @mcp-subdomain Lifecycle
 * @mcp-version 2025-11-25
 */
class ClientCapabilitiesElicitation extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * @var object|null
     */
    protected ?object $form;

    /**
     * @var object|null
     */
    protected ?object $url;

    /**
     * @param object|null $form
     * @param object|null $url
     */
    public function __construct(
        ?object $form = null,
        ?object $url = null
    ) {
        $this->form = $form;
        $this->url = $url;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     form?: object|null,
     *     url?: object|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::asObjectOrNull($data['form'] ?? null),
            self::asObjectOrNull($data['url'] ?? null)
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

        if ($this->form !== null) {
            $result['form'] = $this->form;
        }
        if ($this->url !== null) {
            $result['url'] = $this->url;
        }

        return $result;
    }

    /**
     * @return object|null
     */
    public function getForm(): ?object
    {
        return $this->form;
    }

    /**
     * @return object|null
     */
    public function getUrl(): ?object
    {
        return $this->url;
    }
}
