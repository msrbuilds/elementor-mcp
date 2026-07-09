<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Tasks\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\JSONRPCNotification;
use WP\McpSchema\Common\Protocol\Union\ClientNotificationInterface;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;
use WP\McpSchema\Server\Lifecycle\Union\ServerNotificationInterface;

/**
 * An optional notification from the receiver to the requestor, informing them that a task's status has changed. Receivers are not required to send these notifications.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Common
 * @mcp-subdomain Tasks
 * @mcp-version 2025-11-25
 */
class TaskStatusNotification extends JSONRPCNotification implements ClientNotificationInterface, ServerNotificationInterface
{
    use ValidatesRequiredFields;

    public const METHOD = 'notifications/tasks/status';

    public const DISCRIMINATOR_FIELD = 'method';
    public const DISCRIMINATOR_VALUE = 'notifications/tasks/status';

    /**
     * @var mixed
     */
    protected $typedParams;

    /**
     * @param '2.0' $jsonrpc @since 2025-11-25
     * @param mixed $params @since 2025-11-25
     */
    public function __construct(
        string $jsonrpc,
        $params
    ) {
        parent::__construct(self::METHOD, $jsonrpc, null);
        $this->typedParams = $params;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     jsonrpc: '2.0',
     *     method: 'notifications/tasks/status',
     *     params: mixed
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['jsonrpc', 'params']);

        /** @var '2.0' $jsonrpc */
        $jsonrpc = self::asString($data['jsonrpc']);

        return new self(
            $jsonrpc,
            $data['params']
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

        $result['params'] = $this->typedParams;

        return $result;
    }

    /**
     * @return mixed
     */
    public function getTypedParams()
    {
        return $this->typedParams;
    }
}
