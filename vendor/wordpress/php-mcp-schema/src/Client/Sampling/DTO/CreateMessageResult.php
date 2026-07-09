<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Sampling\DTO;

use WP\McpSchema\Client\Lifecycle\Union\ClientResultInterface;
use WP\McpSchema\Common\Protocol\DTO\Result;
use WP\McpSchema\Common\Protocol\Factory\SamplingMessageContentBlockFactory;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * The client's response to a sampling/createMessage request from the server.
 * The client should inform the user before returning the sampled message, to allow them
 * to inspect the response (human in the loop) and decide whether to allow the server to see it.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified properties: content, role)
 *
 * @mcp-domain Client
 * @mcp-subdomain Sampling
 * @mcp-version 2025-11-25
 */
class CreateMessageResult extends Result implements ClientResultInterface
{
    use ValidatesRequiredFields;

    /**
     * The name of the model that generated the message.
     *
     * @since 2024-11-05
     *
     * @var string
     */
    protected string $model;

    /**
     * The reason why sampling stopped, if known.
     *
     * Standard values:
     * - "endTurn": Natural end of the assistant's turn
     * - "stopSequence": A stop sequence was encountered
     * - "maxTokens": Maximum token limit was reached
     * - "toolUse": The model wants to use one or more tools
     *
     * This field is an open string to allow for provider-specific stop reasons.
     *
     * @since 2024-11-05
     *
     * @var "endTurn"|"stopSequence"|"maxTokens"|"toolUse"|string|null
     */
    protected $stopReason;

    /**
     * @since 2024-11-05
     *
     * @var 'user'|'assistant'
     */
    protected string $role;

    /**
     * @since 2024-11-05
     *
     * @var array<\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface|\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface>
     */
    protected array $content;

    /**
     * @param string $model @since 2024-11-05
     * @param 'user'|'assistant' $role @since 2024-11-05
     * @param array<\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface|\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface> $content @since 2024-11-05
     * @param array<string, mixed>|null $_meta @since 2024-11-05
     * @param "endTurn"|"stopSequence"|"maxTokens"|"toolUse"|string|null $stopReason @since 2024-11-05
     */
    public function __construct(
        string $model,
        string $role,
        array $content,
        ?array $_meta = null,
        $stopReason = null
    ) {
        parent::__construct($_meta);
        $this->model = $model;
        $this->role = $role;
        $this->content = $content;
        $this->stopReason = $stopReason;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|null,
     *     model: string,
     *     stopReason?: "endTurn"|"stopSequence"|"maxTokens"|"toolUse"|string|null,
     *     role: 'user'|'assistant',
     *     content: array<\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface|\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface>
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['model', 'role', 'content']);

        /** @var 'user'|'assistant' $role */
        $role = self::asString($data['role']);

        /** @var array<\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface|\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface> $content */
        $content = array_map(
            static fn($item) => is_array($item)
                ? SamplingMessageContentBlockFactory::fromArray($item)
                : $item,
            self::asArray($data['content'])
        );

        /** @var "endTurn"|"stopSequence"|"maxTokens"|"toolUse"|string|null $stopReason */
        $stopReason = isset($data['stopReason'])
            ? $data['stopReason']
            : null;

        return new self(
            self::asString($data['model']),
            $role,
            $content,
            self::asArrayOrNull($data['_meta'] ?? null),
            $stopReason
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

        $result['model'] = $this->model;
        if ($this->stopReason !== null) {
            $result['stopReason'] = $this->stopReason;
        }
        $result['role'] = $this->role;
        $result['content'] = array_map(static fn($item) => (is_object($item) && method_exists($item, 'toArray')) ? $item->toArray() : $item, $this->content);

        return $result;
    }

    /**
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @return "endTurn"|"stopSequence"|"maxTokens"|"toolUse"|string|null
     */
    public function getStopReason()
    {
        return $this->stopReason;
    }

    /**
     * @return 'user'|'assistant'
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * @return array<\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface|\WP\McpSchema\Common\Protocol\Union\SamplingMessageContentBlockInterface>
     */
    public function getContent(): array
    {
        return $this->content;
    }
}
