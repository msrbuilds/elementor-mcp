<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Prompts\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\RequestParams;
use WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Parameters for a `prompts/get` request.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Server
 * @mcp-subdomain Prompts
 * @mcp-version 2025-11-25
 */
class GetPromptRequestParams extends RequestParams
{
    use ValidatesRequiredFields;

    /**
     * The name of the prompt or prompt template.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $name;

    /**
     * Arguments to use for templating the prompt.
     *
     * @since 2025-11-25
     *
     * @var array<string, string>|null
     */
    protected ?array $arguments;

    /**
     * @param string $name @since 2025-11-25
     * @param \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta @since 2025-11-25
     * @param array<string, string>|null $arguments @since 2025-11-25
     */
    public function __construct(
        string $name,
        ?RequestParamsMeta $_meta = null,
        ?array $arguments = null
    ) {
        parent::__construct($_meta);
        $this->name = $name;
        $this->arguments = $arguments;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|\WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null,
     *     name: string,
     *     arguments?: array<string, string>|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['name']);

        /** @var \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta */
        $_meta = isset($data['_meta'])
            ? (is_array($data['_meta'])
                ? RequestParamsMeta::fromArray(self::asArray($data['_meta']))
                : $data['_meta'])
            : null;

        return new self(
            self::asString($data['name']),
            $_meta,
            self::asStringMapOrNull($data['arguments'] ?? null)
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
     * @return array<string, string>|null
     */
    public function getArguments(): ?array
    {
        return $this->arguments;
    }
}
