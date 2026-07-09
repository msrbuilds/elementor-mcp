<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\Factory;

use WP\McpSchema\Common\Protocol\Union\ClientNotificationInterface;
use WP\McpSchema\Common\Protocol\DTO\CancelledNotification;
use WP\McpSchema\Common\Protocol\DTO\ProgressNotification;
use WP\McpSchema\Common\Protocol\DTO\InitializedNotification;
use WP\McpSchema\Client\Roots\DTO\RootsListChangedNotification;
use WP\McpSchema\Common\Tasks\DTO\TaskStatusNotification;

/**
 * Factory for creating ClientNotification union type instances.
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
final class ClientNotificationFactory
{
    /**
     * Registry mapping discriminator values to implementation classes.
     *
     * @var array<string, class-string<ClientNotificationInterface>>
     */
    public const REGISTRY = [
        'notifications/cancelled' => CancelledNotification::class,
        'notifications/progress' => ProgressNotification::class,
        'notifications/initialized' => InitializedNotification::class,
        'notifications/roots/list_changed' => RootsListChangedNotification::class,
        'notifications/tasks/status' => TaskStatusNotification::class,
    ];

    /**
     * Creates an instance from an array.
     *
     * @param array<string, mixed> $data
     * @return ClientNotificationInterface
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): ClientNotificationInterface
    {
        if (!isset($data['method'])) {
            throw new \InvalidArgumentException('Missing discriminator field: method');
        }

        /** @var string $method */
        $method = $data['method'];
        if (!isset(self::REGISTRY[$method])) {
            throw new \InvalidArgumentException(sprintf(
                "Unknown method value '%s'. Valid values: %s",
                $method,
                implode(', ', array_keys(self::REGISTRY))
            ));
        }

        $class = self::REGISTRY[$method];
        return $class::fromArray($data);
    }

    /**
     * Checks if a method value is supported by this factory.
     *
     * @param string $method
     * @return bool
     */
    public static function supports(string $method): bool
    {
        return isset(self::REGISTRY[$method]);
    }

    /**
     * Returns all supported method values.
     *
     * @return array<string>
     */
    public static function methods(): array
    {
        return array_keys(self::REGISTRY);
    }
}
