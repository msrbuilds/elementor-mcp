<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Lifecycle\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Capabilities a client may support. Known capabilities are defined here, in this schema, but this is not a closed set: any client can define its own, additional capabilities.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (added properties: tasks)
 *
 * @mcp-domain Client
 * @mcp-subdomain Lifecycle
 * @mcp-version 2025-11-25
 */
class ClientCapabilities extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * Experimental, non-standard capabilities that the client supports.
     *
     * @since 2024-11-05
     *
     * @var array<string, object>|null
     */
    protected ?array $experimental;

    /**
     * Present if the client supports listing roots.
     *
     * @since 2024-11-05
     *
     * @var \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesRoots|null
     */
    protected ?ClientCapabilitiesRoots $roots;

    /**
     * Present if the client supports sampling from an LLM.
     *
     * @since 2024-11-05
     *
     * @var \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesSampling|null
     */
    protected ?ClientCapabilitiesSampling $sampling;

    /**
     * Present if the client supports elicitation from the server.
     *
     * @since 2025-06-18
     *
     * @var \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesElicitation|null
     */
    protected ?ClientCapabilitiesElicitation $elicitation;

    /**
     * Present if the client supports task-augmented requests.
     *
     * @since 2025-11-25
     *
     * @var \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesTasks|null
     */
    protected ?ClientCapabilitiesTasks $tasks;

    /**
     * @param array<string, object>|null $experimental @since 2024-11-05
     * @param \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesRoots|null $roots @since 2024-11-05
     * @param \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesSampling|null $sampling @since 2024-11-05
     * @param \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesElicitation|null $elicitation @since 2025-06-18
     * @param \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesTasks|null $tasks @since 2025-11-25
     */
    public function __construct(
        ?array $experimental = null,
        ?ClientCapabilitiesRoots $roots = null,
        ?ClientCapabilitiesSampling $sampling = null,
        ?ClientCapabilitiesElicitation $elicitation = null,
        ?ClientCapabilitiesTasks $tasks = null
    ) {
        $this->experimental = $experimental;
        $this->roots = $roots;
        $this->sampling = $sampling;
        $this->elicitation = $elicitation;
        $this->tasks = $tasks;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     experimental?: array<string, object>|null,
     *     roots?: array<string, mixed>|\WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesRoots|null,
     *     sampling?: array<string, mixed>|\WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesSampling|null,
     *     elicitation?: array<string, mixed>|\WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesElicitation|null,
     *     tasks?: array<string, mixed>|\WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesTasks|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        /** @var \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesRoots|null $roots */
        $roots = isset($data['roots'])
            ? (is_array($data['roots'])
                ? ClientCapabilitiesRoots::fromArray(self::asArray($data['roots']))
                : $data['roots'])
            : null;

        /** @var \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesSampling|null $sampling */
        $sampling = isset($data['sampling'])
            ? (is_array($data['sampling'])
                ? ClientCapabilitiesSampling::fromArray(self::asArray($data['sampling']))
                : $data['sampling'])
            : null;

        /** @var \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesElicitation|null $elicitation */
        $elicitation = isset($data['elicitation'])
            ? (is_array($data['elicitation'])
                ? ClientCapabilitiesElicitation::fromArray(self::asArray($data['elicitation']))
                : $data['elicitation'])
            : null;

        /** @var \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesTasks|null $tasks */
        $tasks = isset($data['tasks'])
            ? (is_array($data['tasks'])
                ? ClientCapabilitiesTasks::fromArray(self::asArray($data['tasks']))
                : $data['tasks'])
            : null;

        return new self(
            self::asObjectMapOrNull($data['experimental'] ?? null),
            $roots,
            $sampling,
            $elicitation,
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
        if ($this->roots !== null) {
            $result['roots'] = $this->roots->toArray();
        }
        if ($this->sampling !== null) {
            $result['sampling'] = $this->sampling->toArray();
        }
        if ($this->elicitation !== null) {
            $result['elicitation'] = $this->elicitation->toArray();
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
     * @return \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesRoots|null
     */
    public function getRoots(): ?ClientCapabilitiesRoots
    {
        return $this->roots;
    }

    /**
     * @return \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesSampling|null
     */
    public function getSampling(): ?ClientCapabilitiesSampling
    {
        return $this->sampling;
    }

    /**
     * @return \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesElicitation|null
     */
    public function getElicitation(): ?ClientCapabilitiesElicitation
    {
        return $this->elicitation;
    }

    /**
     * @return \WP\McpSchema\Client\Lifecycle\DTO\ClientCapabilitiesTasks|null
     */
    public function getTasks(): ?ClientCapabilitiesTasks
    {
        return $this->tasks;
    }
}
