<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\Factory;

use WP\McpSchema\Common\Protocol\Union\ServerRequestInterface;
use WP\McpSchema\Common\Protocol\DTO\PingRequest;
use WP\McpSchema\Client\Sampling\DTO\CreateMessageRequest;
use WP\McpSchema\Client\Roots\DTO\ListRootsRequest;
use WP\McpSchema\Client\Elicitation\DTO\ElicitRequest;
use WP\McpSchema\Common\Tasks\DTO\GetTaskRequest;
use WP\McpSchema\Common\Protocol\DTO\GetTaskPayloadRequest;
use WP\McpSchema\Common\Tasks\DTO\ListTasksRequest;
use WP\McpSchema\Common\Tasks\DTO\CancelTaskRequest;

/**
 * Factory for creating ServerRequest union type instances.
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
final class ServerRequestFactory
{
    /**
     * Registry mapping discriminator values to implementation classes.
     *
     * @var array<string, class-string<ServerRequestInterface>>
     */
    public const REGISTRY = [
        'ping' => PingRequest::class,
        'sampling/createMessage' => CreateMessageRequest::class,
        'roots/list' => ListRootsRequest::class,
        'elicitation/create' => ElicitRequest::class,
        'tasks/get' => GetTaskRequest::class,
        'tasks/result' => GetTaskPayloadRequest::class,
        'tasks/list' => ListTasksRequest::class,
        'tasks/cancel' => CancelTaskRequest::class,
    ];

    /**
     * Creates an instance from an array.
     *
     * @param array<string, mixed> $data
     * @return ServerRequestInterface
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): ServerRequestInterface
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
