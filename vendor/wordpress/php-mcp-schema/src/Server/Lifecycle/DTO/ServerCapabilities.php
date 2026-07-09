<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Lifecycle\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Capabilities that a server may support. Known capabilities are defined here, in this schema, but this is not a closed set: any server can define its own, additional capabilities.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (added properties: tasks)
 *
 * @mcp-domain Server
 * @mcp-subdomain Lifecycle
 * @mcp-version 2025-11-25
 */
class ServerCapabilities extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * Experimental, non-standard capabilities that the server supports.
     *
     * @since 2024-11-05
     *
     * @var array<string, object>|null
     */
    protected ?array $experimental;

    /**
     * Present if the server supports sending log messages to the client.
     *
     * @since 2024-11-05
     *
     * @var object|null
     */
    protected ?object $logging;

    /**
     * Present if the server supports argument autocompletion suggestions.
     *
     * @since 2025-03-26
     *
     * @var object|null
     */
    protected ?object $completions;

    /**
     * Present if the server offers any prompt templates.
     *
     * @since 2024-11-05
     *
     * @var \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesPrompts|null
     */
    protected ?ServerCapabilitiesPrompts $prompts;

    /**
     * Present if the server offers any resources to read.
     *
     * @since 2024-11-05
     *
     * @var \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesResources|null
     */
    protected ?ServerCapabilitiesResources $resources;

    /**
     * Present if the server offers any tools to call.
     *
     * @since 2024-11-05
     *
     * @var \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesTools|null
     */
    protected ?ServerCapabilitiesTools $tools;

    /**
     * Present if the server supports task-augmented requests.
     *
     * @since 2025-11-25
     *
     * @var \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesTasks|null
     */
    protected ?ServerCapabilitiesTasks $tasks;

    /**
     * @param array<string, object>|null $experimental @since 2024-11-05
     * @param object|null $logging @since 2024-11-05
     * @param object|null $completions @since 2025-03-26
     * @param \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesPrompts|null $prompts @since 2024-11-05
     * @param \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesResources|null $resources @since 2024-11-05
     * @param \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesTools|null $tools @since 2024-11-05
     * @param \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesTasks|null $tasks @since 2025-11-25
     */
    public function __construct(
        ?array $experimental = null,
        ?object $logging = null,
        ?object $completions = null,
        ?ServerCapabilitiesPrompts $prompts = null,
        ?ServerCapabilitiesResources $resources = null,
        ?ServerCapabilitiesTools $tools = null,
        ?ServerCapabilitiesTasks $tasks = null
    ) {
        $this->experimental = $experimental;
        $this->logging = $logging;
        $this->completions = $completions;
        $this->prompts = $prompts;
        $this->resources = $resources;
        $this->tools = $tools;
        $this->tasks = $tasks;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     experimental?: array<string, object>|null,
     *     logging?: object|null,
     *     completions?: object|null,
     *     prompts?: array<string, mixed>|\WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesPrompts|null,
     *     resources?: array<string, mixed>|\WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesResources|null,
     *     tools?: array<string, mixed>|\WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesTools|null,
     *     tasks?: array<string, mixed>|\WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesTasks|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        /** @var \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesPrompts|null $prompts */
        $prompts = isset($data['prompts'])
            ? (is_array($data['prompts'])
                ? ServerCapabilitiesPrompts::fromArray(self::asArray($data['prompts']))
                : $data['prompts'])
            : null;

        /** @var \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesResources|null $resources */
        $resources = isset($data['resources'])
            ? (is_array($data['resources'])
                ? ServerCapabilitiesResources::fromArray(self::asArray($data['resources']))
                : $data['resources'])
            : null;

        /** @var \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesTools|null $tools */
        $tools = isset($data['tools'])
            ? (is_array($data['tools'])
                ? ServerCapabilitiesTools::fromArray(self::asArray($data['tools']))
                : $data['tools'])
            : null;

        /** @var \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesTasks|null $tasks */
        $tasks = isset($data['tasks'])
            ? (is_array($data['tasks'])
                ? ServerCapabilitiesTasks::fromArray(self::asArray($data['tasks']))
                : $data['tasks'])
            : null;

        return new self(
            self::asObjectMapOrNull($data['experimental'] ?? null),
            self::asObjectOrNull($data['logging'] ?? null),
            self::asObjectOrNull($data['completions'] ?? null),
            $prompts,
            $resources,
            $tools,
            $tasks
        );
    }

    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->experimental !== null) {
            $result['experimental'] = $this->experimental;
        }
        if ($this->logging !== null) {
            $result['logging'] = $this->logging;
        }
        if ($this->completions !== null) {
            $result['completions'] = $this->completions;
        }
        if ($this->prompts !== null) {
            $result['prompts'] = $this->prompts->toArray();
        }
        if ($this->resources !== null) {
            $result['resources'] = $this->resources->toArray();
        }
        if ($this->tools !== null) {
            $result['tools'] = $this->tools->toArray();
        }
        if ($this->tasks !== null) {
            $result['tasks'] = $this->tasks->toArray();
        }

        return $result;
    }

    /**
     * @return array<string, object>|null
     */
    public function getExperimental(): ?array
    {
        return $this->experimental;
    }

    /**
     * @return object|null
     */
    public function getLogging(): ?object
    {
        return $this->logging;
    }

    /**
     * @return object|null
     */
    public function getCompletions(): ?object
    {
        return $this->completions;
    }

    /**
     * @return \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesPrompts|null
     */
    public function getPrompts(): ?ServerCapabilitiesPrompts
    {
        return $this->prompts;
    }

    /**
     * @return \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesResources|null
     */
    public function getResources(): ?ServerCapabilitiesResources
    {
        return $this->resources;
    }

    /**
     * @return \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesTools|null
     */
    public function getTools(): ?ServerCapabilitiesTools
    {
        return $this->tools;
    }

    /**
     * @return \WP\McpSchema\Server\Lifecycle\DTO\ServerCapabilitiesTasks|null
     */
    public function getTasks(): ?ServerCapabilitiesTasks
    {
        return $this->tasks;
    }
}
