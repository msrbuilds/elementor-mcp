<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Core\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * A reference to a resource or resource template definition.
 *
 * @since 2025-06-18
 *
 * @mcp-domain Server
 * @mcp-subdomain Core
 * @mcp-version 2025-11-25
 */
class ResourceTemplateReference extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    public const TYPE = 'ref/resource';

    /**
     * @since 2025-06-18
     *
     * @var 'ref/resource'
     */
    protected string $type;

    /**
     * The URI or URI template of the resource.
     *
     * @since 2025-06-18
     *
     * @var string
     */
    protected string $uri;

    /**
     * @param string $uri @since 2025-06-18
     */
    public function __construct(
        string $uri
    ) {
        $this->type = self::TYPE;
        $this->uri = $uri;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     type: 'ref/resource',
     *     uri: string
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['uri']);

        return new self(
            self::asString($data['uri'])
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
        $result['uri'] = $this->uri;

        return $result;
    }

    /**
     * @return 'ref/resource'
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }
}
