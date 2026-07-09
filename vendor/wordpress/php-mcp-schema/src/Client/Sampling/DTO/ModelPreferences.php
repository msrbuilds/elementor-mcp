<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Sampling\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * The server's preferences for model selection, requested of the client during sampling.
 *
 * Because LLMs can vary along multiple dimensions, choosing the "best" model is
 * rarely straightforward.  Different models excel in different areas—some are
 * faster but less capable, others are more capable but more expensive, and so
 * on. This interface allows servers to express their priorities across multiple
 * dimensions to help clients make an appropriate selection for their use case.
 *
 * These preferences are always advisory. The client MAY ignore them. It is also
 * up to the client to decide how to interpret these preferences and how to
 * balance them against other considerations.
 *
 * @since 2024-11-05
 * @last-updated 2025-11-25 (modified property: hints)
 *
 * @mcp-domain Client
 * @mcp-subdomain Sampling
 * @mcp-version 2025-11-25
 */
class ModelPreferences extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * Optional hints to use for model selection.
     *
     * If multiple hints are specified, the client MUST evaluate them in order
     * (such that the first match is taken).
     *
     * The client SHOULD prioritize these hints over the numeric priorities, but
     * MAY still use the priorities to select from ambiguous matches.
     *
     * @since 2024-11-05
     *
     * @var array<\WP\McpSchema\Client\Sampling\DTO\ModelHint>|null
     */
    protected ?array $hints;

    /**
     * How much to prioritize cost when selecting a model. A value of 0 means cost
     * is not important, while a value of 1 means cost is the most important
     * factor.
     *
     * @since 2024-11-05
     *
     * @var float|null
     */
    protected ?float $costPriority;

    /**
     * How much to prioritize sampling speed (latency) when selecting a model. A
     * value of 0 means speed is not important, while a value of 1 means speed is
     * the most important factor.
     *
     * @since 2024-11-05
     *
     * @var float|null
     */
    protected ?float $speedPriority;

    /**
     * How much to prioritize intelligence and capabilities when selecting a
     * model. A value of 0 means intelligence is not important, while a value of 1
     * means intelligence is the most important factor.
     *
     * @since 2024-11-05
     *
     * @var float|null
     */
    protected ?float $intelligencePriority;

    /**
     * @param array<\WP\McpSchema\Client\Sampling\DTO\ModelHint>|null $hints @since 2024-11-05
     * @param float|null $costPriority @since 2024-11-05
     * @param float|null $speedPriority @since 2024-11-05
     * @param float|null $intelligencePriority @since 2024-11-05
     */
    public function __construct(
        ?array $hints = null,
        ?float $costPriority = null,
        ?float $speedPriority = null,
        ?float $intelligencePriority = null
    ) {
        $this->hints = $hints;
        $this->costPriority = $costPriority;
        $this->speedPriority = $speedPriority;
        $this->intelligencePriority = $intelligencePriority;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     hints?: array<array<string, mixed>|\WP\McpSchema\Client\Sampling\DTO\ModelHint>|null,
     *     costPriority?: float|null,
     *     speedPriority?: float|null,
     *     intelligencePriority?: float|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        /** @var array<\WP\McpSchema\Client\Sampling\DTO\ModelHint>|null $hints */
        $hints = isset($data['hints'])
            ? array_map(
                static fn($item) => is_array($item)
                    ? ModelHint::fromArray($item)
                    : $item,
                self::asArray($data['hints'])
            )
            : null;

        return new self(
            $hints,
            self::asFloatOrNull($data['costPriority'] ?? null),
            self::asFloatOrNull($data['speedPriority'] ?? null),
            self::asFloatOrNull($data['intelligencePriority'] ?? null)
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

        if ($this->hints !== null) {
            $result['hints'] = array_map(static fn($item) => $item->toArray(), $this->hints);
        }
        if ($this->costPriority !== null) {
            $result['costPriority'] = $this->costPriority;
        }
        if ($this->speedPriority !== null) {
            $result['speedPriority'] = $this->speedPriority;
        }
        if ($this->intelligencePriority !== null) {
            $result['intelligencePriority'] = $this->intelligencePriority;
        }

        return $result;
    }

    /**
     * @return array<\WP\McpSchema\Client\Sampling\DTO\ModelHint>|null
     */
    public function getHints(): ?array
    {
        return $this->hints;
    }

    /**
     * @return float|null
     */
    public function getCostPriority(): ?float
    {
        return $this->costPriority;
    }

    /**
     * @return float|null
     */
    public function getSpeedPriority(): ?float
    {
        return $this->speedPriority;
    }

    /**
     * @return float|null
     */
    public function getIntelligencePriority(): ?float
    {
        return $this->intelligencePriority;
    }
}
