<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Contracts;

/**
 * Interface for objects that support array transformation.
 *
 * @mcp-version 2025-11-25
 */
interface WithArrayTransformationInterface
{
    /**
     * Converts the object to an array representation.
     *
     * @return array<string, mixed> The array representation.
     */
    public function toArray(): array;

    /**
     * Creates an instance from array data.
     *
     * @param array<string, mixed> $data The array data.
     * @return static The created instance.
     */
    public static function fromArray(array $data);
}
