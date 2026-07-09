<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Tasks\DTO;

use WP\McpSchema\Common\Protocol\DTO\PaginatedRequest;
use WP\McpSchema\Common\Protocol\DTO\PaginatedRequestParams;
use WP\McpSchema\Common\Protocol\Union\ClientRequestInterface;
use WP\McpSchema\Common\Protocol\Union\ServerRequestInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * A request to retrieve a list of tasks.
 *
 * Note: This class is structurally identical to PaginatedRequest.
 * It exists as a separate type for semantic distinction per MCP specification.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Common
 * @mcp-subdomain Tasks
 * @mcp-version 2025-11-25
 * @see PaginatedRequest
 */
class ListTasksRequest extends PaginatedRequest implements ClientRequestInterface, ServerRequestInterface
{
    use ValidatesRequiredFields;

    public const METHOD = 'tasks/list';

    public const DISCRIMINATOR_FIELD = 'method';
    public const DISCRIMINATOR_VALUE = 'tasks/list';

    /**
     * @param '2.0' $jsonrpc @since 2025-11-25
     * @param string|number $id @since 2025-11-25
     * @param \WP\McpSchema\Common\Protocol\DTO\PaginatedRequestParams|null $params @since 2025-11-25
     */
    public function __construct(
        string $jsonrpc,
        $id,
        ?PaginatedRequestParams $params = null
    ) {
        parent::__construct($jsonrpc, $id, self::METHOD, $params);
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     params?: array<string, mixed>|\WP\McpSchema\Common\Protocol\DTO\PaginatedRequestParams|null,
     *     jsonrpc: '2.0',
     *     id: string|number,
     *     method: 'tasks/list'
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['jsonrpc', 'id']);

        /** @var '2.0' $jsonrpc */
        $jsonrpc = self::asString($data['jsonrpc']);

        /** @var string|number $id */
        $id = self::asStringOrNumber($data['id']);

        /** @var \WP\McpSchema\Common\Protocol\DTO\PaginatedRequestParams|null $params */
        $params = isset($data['params'])
            ? (is_array($data['params'])
                ? PaginatedRequestParams::fromArray(self::asArray($data['params']))
                : $data['params'])
            : null;

        return new self(
            $jsonrpc,
            $id,
            $params
        );
    }

    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = parent::toArray();

        return $result;
    }
}
