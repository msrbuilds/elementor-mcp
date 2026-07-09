<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Sampling\DTO;

use WP\McpSchema\Client\Tasks\DTO\TaskMetadata;
use WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta;
use WP\McpSchema\Common\Tasks\DTO\TaskAugmentedRequestParams;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;
use WP\McpSchema\Server\Tools\DTO\Tool;

/**
 * Parameters for a `sampling/createMessage` request.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Client
 * @mcp-subdomain Sampling
 * @mcp-version 2025-11-25
 */
class CreateMessageRequestParams extends TaskAugmentedRequestParams
{
    use ValidatesRequiredFields;

    /**
     * @since 2025-11-25
     *
     * @var array<\WP\McpSchema\Client\Sampling\DTO\SamplingMessage>
     */
    protected array $messages;

    /**
     * The server's preferences for which model to select. The client MAY ignore these preferences.
     *
     * @since 2025-11-25
     *
     * @var \WP\McpSchema\Client\Sampling\DTO\ModelPreferences|null
     */
    protected ?ModelPreferences $modelPreferences;

    /**
     * An optional system prompt the server wants to use for sampling. The client MAY modify or omit this prompt.
     *
     * @since 2025-11-25
     *
     * @var string|null
     */
    protected ?string $systemPrompt;

    /**
     * A request to include context from one or more MCP servers (including the caller), to be attached to the prompt.
     * The client MAY ignore this request.
     *
     * Default is "none". Values "thisServer" and "allServers" are soft-deprecated. Servers SHOULD only use these values if the client
     * declares ClientCapabilities.sampling.context. These values may be removed in future spec releases.
     *
     * @since 2025-11-25
     *
     * @var 'none'|'thisServer'|'allServers'|null
     */
    protected ?string $includeContext;

    /**
     * @since 2025-11-25
     *
     * @var float|null
     */
    protected ?float $temperature;

    /**
     * The requested maximum number of tokens to sample (to prevent runaway completions).
     *
     * The client MAY choose to sample fewer tokens than the requested maximum.
     *
     * @since 2025-11-25
     *
     * @var float
     */
    protected float $maxTokens;

    /**
     * @since 2025-11-25
     *
     * @var array<string>|null
     */
    protected ?array $stopSequences;

    /**
     * Optional metadata to pass through to the LLM provider. The format of this metadata is provider-specific.
     *
     * @since 2025-11-25
     *
     * @var object|null
     */
    protected ?object $metadata;

    /**
     * Tools that the model may use during generation.
     * The client MUST return an error if this field is provided but ClientCapabilities.sampling.tools is not declared.
     *
     * @since 2025-11-25
     *
     * @var array<\WP\McpSchema\Server\Tools\DTO\Tool>|null
     */
    protected ?array $tools;

    /**
     * Controls how the model uses tools.
     * The client MUST return an error if this field is provided but ClientCapabilities.sampling.tools is not declared.
     * Default is `{ mode: "auto" }`.
     *
     * @since 2025-11-25
     *
     * @var \WP\McpSchema\Client\Sampling\DTO\ToolChoice|null
     */
    protected ?ToolChoice $toolChoice;

    /**
     * @param array<\WP\McpSchema\Client\Sampling\DTO\SamplingMessage> $messages @since 2025-11-25
     * @param float $maxTokens @since 2025-11-25
     * @param \WP\McpSchema\Client\Tasks\DTO\TaskMetadata|null $task @since 2025-11-25
     * @param \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta @since 2025-11-25
     * @param \WP\McpSchema\Client\Sampling\DTO\ModelPreferences|null $modelPreferences @since 2025-11-25
     * @param string|null $systemPrompt @since 2025-11-25
     * @param 'none'|'thisServer'|'allServers'|null $includeContext @since 2025-11-25
     * @param float|null $temperature @since 2025-11-25
     * @param array<string>|null $stopSequences @since 2025-11-25
     * @param object|null $metadata @since 2025-11-25
     * @param array<\WP\McpSchema\Server\Tools\DTO\Tool>|null $tools @since 2025-11-25
     * @param \WP\McpSchema\Client\Sampling\DTO\ToolChoice|null $toolChoice @since 2025-11-25
     */
    public function __construct(
        array $messages,
        float $maxTokens,
        ?TaskMetadata $task = null,
        ?RequestParamsMeta $_meta = null,
        ?ModelPreferences $modelPreferences = null,
        ?string $systemPrompt = null,
        ?string $includeContext = null,
        ?float $temperature = null,
        ?array $stopSequences = null,
        ?object $metadata = null,
        ?array $tools = null,
        ?ToolChoice $toolChoice = null
    ) {
        parent::__construct($_meta, $task);
        $this->messages = $messages;
        $this->maxTokens = $maxTokens;
        $this->modelPreferences = $modelPreferences;
        $this->systemPrompt = $systemPrompt;
        $this->includeContext = $includeContext;
        $this->temperature = $temperature;
        $this->stopSequences = $stopSequences;
        $this->metadata = $metadata;
        $this->tools = $tools;
        $this->toolChoice = $toolChoice;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     task?: array<string, mixed>|\WP\McpSchema\Client\Tasks\DTO\TaskMetadata|null,
     *     _meta?: array<string, mixed>|\WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null,
     *     messages: array<array<string, mixed>|\WP\McpSchema\Client\Sampling\DTO\SamplingMessage>,
     *     modelPreferences?: array<string, mixed>|\WP\McpSchema\Client\Sampling\DTO\ModelPreferences|null,
     *     systemPrompt?: string|null,
     *     includeContext?: 'none'|'thisServer'|'allServers'|null,
     *     temperature?: float|null,
     *     maxTokens: float,
     *     stopSequences?: array<string>|null,
     *     metadata?: object|null,
     *     tools?: array<array<string, mixed>|\WP\McpSchema\Server\Tools\DTO\Tool>|null,
     *     toolChoice?: array<string, mixed>|\WP\McpSchema\Client\Sampling\DTO\ToolChoice|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['messages', 'maxTokens']);

        /** @var array<\WP\McpSchema\Client\Sampling\DTO\SamplingMessage> $messages */
        $messages = array_map(
            static fn($item) => is_array($item)
                ? SamplingMessage::fromArray($item)
                : $item,
            self::asArray($data['messages'])
        );

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

        /** @var \WP\McpSchema\Client\Sampling\DTO\ModelPreferences|null $modelPreferences */
        $modelPreferences = isset($data['modelPreferences'])
            ? (is_array($data['modelPreferences'])
                ? ModelPreferences::fromArray(self::asArray($data['modelPreferences']))
                : $data['modelPreferences'])
            : null;

        /** @var 'none'|'thisServer'|'allServers'|null $includeContext */
        $includeContext = isset($data['includeContext'])
            ? self::asStringOrNull($data['includeContext'])
            : null;

        /** @var array<\WP\McpSchema\Server\Tools\DTO\Tool>|null $tools */
        $tools = isset($data['tools'])
            ? array_map(
                static fn($item) => is_array($item)
                    ? Tool::fromArray($item)
                    : $item,
                self::asArray($data['tools'])
            )
            : null;

        /** @var \WP\McpSchema\Client\Sampling\DTO\ToolChoice|null $toolChoice */
        $toolChoice = isset($data['toolChoice'])
            ? (is_array($data['toolChoice'])
                ? ToolChoice::fromArray(self::asArray($data['toolChoice']))
                : $data['toolChoice'])
            : null;

        return new self(
            $messages,
            self::asFloat($data['maxTokens']),
            $task,
            $_meta,
            $modelPreferences,
            self::asStringOrNull($data['systemPrompt'] ?? null),
            $includeContext,
            self::asFloatOrNull($data['temperature'] ?? null),
            self::asStringArrayOrNull($data['stopSequences'] ?? null),
            self::asObjectOrNull($data['metadata'] ?? null),
            $tools,
            $toolChoice
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

        $result['messages'] = array_map(static fn($item) => $item->toArray(), $this->messages);
        if ($this->modelPreferences !== null) {
            $result['modelPreferences'] = $this->modelPreferences->toArray();
        }
        if ($this->systemPrompt !== null) {
            $result['systemPrompt'] = $this->systemPrompt;
        }
        if ($this->includeContext !== null) {
            $result['includeContext'] = $this->includeContext;
        }
        if ($this->temperature !== null) {
            $result['temperature'] = $this->temperature;
        }
        $result['maxTokens'] = $this->maxTokens;
        if ($this->stopSequences !== null) {
            $result['stopSequences'] = $this->stopSequences;
        }
        if ($this->metadata !== null) {
            $result['metadata'] = $this->metadata;
        }
        if ($this->tools !== null) {
            $result['tools'] = array_map(static fn($item) => $item->toArray(), $this->tools);
        }
        if ($this->toolChoice !== null) {
            $result['toolChoice'] = $this->toolChoice->toArray();
        }

        return $result;
    }

    /**
     * @return array<\WP\McpSchema\Client\Sampling\DTO\SamplingMessage>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @return \WP\McpSchema\Client\Sampling\DTO\ModelPreferences|null
     */
    public function getModelPreferences(): ?ModelPreferences
    {
        return $this->modelPreferences;
    }

    /**
     * @return string|null
     */
    public function getSystemPrompt(): ?string
    {
        return $this->systemPrompt;
    }

    /**
     * @return 'none'|'thisServer'|'allServers'|null
     */
    public function getIncludeContext(): ?string
    {
        return $this->includeContext;
    }

    /**
     * @return float|null
     */
    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    /**
     * @return float
     */
    public function getMaxTokens(): float
    {
        return $this->maxTokens;
    }

    /**
     * @return array<string>|null
     */
    public function getStopSequences(): ?array
    {
        return $this->stopSequences;
    }

    /**
     * @return object|null
     */
    public function getMetadata(): ?object
    {
        return $this->metadata;
    }

    /**
     * @return array<\WP\McpSchema\Server\Tools\DTO\Tool>|null
     */
    public function getTools(): ?array
    {
        return $this->tools;
    }

    /**
     * @return \WP\McpSchema\Client\Sampling\DTO\ToolChoice|null
     */
    public function getToolChoice(): ?ToolChoice
    {
        return $this->toolChoice;
    }
}
