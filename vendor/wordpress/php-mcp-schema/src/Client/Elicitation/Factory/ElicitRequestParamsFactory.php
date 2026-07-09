<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\Factory;

use WP\McpSchema\Client\Elicitation\Union\ElicitRequestParamsInterface;
use WP\McpSchema\Client\Elicitation\DTO\ElicitRequestFormParams;
use WP\McpSchema\Client\Elicitation\DTO\ElicitRequestURLParams;

/**
 * Factory for creating ElicitRequestParams union type instances.
 *
 * The parameters for a request to elicit additional information from the user via the client.
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
final class ElicitRequestParamsFactory
{
    /**
     * Registry mapping discriminator values to implementation classes.
     *
     * @var array<string, class-string<ElicitRequestParamsInterface>>
     */
    public const REGISTRY = [
        'form' => ElicitRequestFormParams::class,
        'url' => ElicitRequestURLParams::class,
    ];

    /**
     * Creates an instance from an array.
     *
     * @param array<string, mixed> $data
     * @return ElicitRequestParamsInterface
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): ElicitRequestParamsInterface
    {
        if (!isset($data['mode'])) {
            throw new \InvalidArgumentException('Missing discriminator field: mode');
        }

        /** @var string $mode */
        $mode = $data['mode'];
        if (!isset(self::REGISTRY[$mode])) {
            throw new \InvalidArgumentException(sprintf(
                "Unknown mode value '%s'. Valid values: %s",
                $mode,
                implode(', ', array_keys(self::REGISTRY))
            ));
        }

        $class = self::REGISTRY[$mode];
        return $class::fromArray($data);
    }

    /**
     * Checks if a mode value is supported by this factory.
     *
     * @param string $mode
     * @return bool
     */
    public static function supports(string $mode): bool
    {
        return isset(self::REGISTRY[$mode]);
    }

    /**
     * Returns all supported mode values.
     *
     * @return array<string>
     */
    public static function modes(): array
    {
        return array_keys(self::REGISTRY);
    }
}
