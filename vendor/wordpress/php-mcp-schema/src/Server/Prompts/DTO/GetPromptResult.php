<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Prompts\DTO;

use WP\McpSchema\Common\Protocol\DTO\Result;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;
use WP\McpSchema\Server\Lifecycle\Union\ServerResultInterface;

/**
 * The server's response to a prompts/get request from the client.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: messages)
 *
 * @mcp-domain Server
 * @mcp-subdomain Prompts
 * @mcp-version 2025-11-25
 */
class GetPromptResult extends Result implements ServerResultInterface
{
    use ValidatesRequiredFields;

    /**
     * An optional description for the prompt.
     *
     * @since 2024-11-05
     *
     * @var string|null
     */
    protected ?string $description;

    /**
     * @since 2024-11-05
     *
     * @var array<\WP\McpSchema\Server\Prompts\DTO\PromptMessage>
     */
    protected array $messages;

    /**
     * @param array<\WP\McpSchema\Server\Prompts\DTO\PromptMessage> $messages @since 2024-11-05
     * @param array<string, mixed>|null $_meta @since 2024-11-05
     * @param string|null $description @since 2024-11-05
     */
    public function __construct(
        array $messages,
        ?array $_meta = null,
        ?string $description = null
    ) {
        parent::__construct($_meta);
        $this->messages = $messages;
        $this->description = $description;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|null,
     *     description?: string|null,
     *     messages: array<array<string, mixed>|\WP\McpSchema\Server\Prompts\DTO\PromptMessage>
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['messages']);

        /** @var array<\WP\McpSchema\Server\Prompts\DTO\PromptMessage> $messages */
        $messages = array_map(
            static fn($item) => is_array($item)
                ? PromptMessage::fromArray($item)
                : $item,
            self::asArray($data['messages'])
        );

        return new self(
            $messages,
            self::asArrayOrNull($data['_meta'] ?? null),
            self::asStringOrNull($data['description'] ?? null)
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

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }
        $result['messages'] = array_map(static fn($item) => $item->toArray(), $this->messages);

        return $result;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<\WP\McpSchema\Server\Prompts\DTO\PromptMessage>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
