<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Tools\DTO;

use WP\McpSchema\Client\Tasks\DTO\TaskMetadata;
use WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta;
use WP\McpSchema\Common\Tasks\DTO\TaskAugmentedRequestParams;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Parameters for a `tools/call` request.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Server
 * @mcp-subdomain Tools
 * @mcp-version 2025-11-25
 */
class CallToolRequestParams extends TaskAugmentedRequestParams
{
    use ValidatesRequiredFields;

    /**
     * The name of the tool.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $name;

    /**
     * Arguments to use for the tool call.
     *
     * @since 2025-11-25
     *
     * @var array<string, mixed>|null
     */
    protected ?array $arguments;

    /**
     * @param string $name @since 2025-11-25
     * @param \WP\McpSchema\Client\Tasks\DTO\TaskMetadata|null $task @since 2025-11-25
     * @param \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta @since 2025-11-25
     * @param array<string, mixed>|null $arguments @since 2025-11-25
     */
    public function __construct(
        string $name,
        ?TaskMetadata $task = null,
        ?RequestParamsMeta $_meta = null,
        ?array $arguments = null
    ) {
        parent::__construct($_meta, $task);
        $this->name = $name;
        $this->arguments = $arguments;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     task?: array<string, mixed>|\WP\McpSchema\Client\Tasks\DTO\TaskMetadata|null,
     *     _meta?: array<string, mixed>|\WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null,
     *     name: string,
     *     arguments?: array<string, mixed>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['name']);

        /** @var \WP\McpSchema\Client\Tasks\DTO\TaskMetadata|null $task */
        $task = isset($data['task'])
            ? (is_array($data['task'])
                ? TaskMetadata::fromArray(self::asArray($data['task']))
                : $data['task'])
            : null;

        /** @var \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta */
        $_meta = isset($data['_meta'])
            ? (is_array($data['_meta'])
                ? RequestParamsMeta::fromArray(self::asArray($data['_meta']))
                : $data['_meta'])
            : null;

        return new self(
            self::asString($data['name']),
            $task,
            $_meta,
            self::asArrayOrNull($data['arguments'] ?? null)
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

        $result['name'] = $this->name;
        if ($this->arguments !== null) {
            $result['arguments'] = $this->arguments;
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getArguments(): ?array
    {
        return $this->arguments;
    }
}
