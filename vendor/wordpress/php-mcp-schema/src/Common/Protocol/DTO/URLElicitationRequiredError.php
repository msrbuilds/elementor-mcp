<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * An error response that indicates that the server requires the client to provide additional information via an elicitation request.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class URLElicitationRequiredError extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * @since 2025-11-25
     *
     * @var mixed
     */
    protected $error;

    /**
     * @param mixed $error @since 2025-11-25
     */
    public function __construct(
        $error
    ) {
        $this->error = $error;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     error: mixed
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['error']);

        return new self(
            $data['error']
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

        $result['error'] = $this->error;

        return $result;
    }

    /**
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }
}
