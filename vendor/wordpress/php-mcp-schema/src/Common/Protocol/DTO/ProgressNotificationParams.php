<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\NotificationParams;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Parameters for a `notifications/progress` notification.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class ProgressNotificationParams extends NotificationParams
{
    use ValidatesRequiredFields;

    /**
     * The progress token which was given in the initial request, used to associate this notification with the request that is proceeding.
     *
     * @since 2025-11-25
     *
     * @var string|number
     */
    protected $progressToken;

    /**
     * The progress thus far. This should increase every time progress is made, even if the total is unknown.
     *
     * @since 2025-11-25
     *
     * @var float
     */
    protected float $progress;

    /**
     * Total number of items to process (or total progress required), if known.
     *
     * @since 2025-11-25
     *
     * @var int|null
     */
    protected ?int $total;

    /**
     * An optional message describing the current progress.
     *
     * @since 2025-11-25
     *
     * @var string|null
     */
    protected ?string $message;

    /**
     * @param string|number $progressToken @since 2025-11-25
     * @param float $progress @since 2025-11-25
     * @param array<string, mixed>|null $_meta @since 2025-11-25
     * @param int|null $total @since 2025-11-25
     * @param string|null $message @since 2025-11-25
     */
    public function __construct(
        $progressToken,
        float $progress,
        ?array $_meta = null,
        ?int $total = null,
        ?string $message = null
    ) {
        parent::__construct($_meta);
        $this->progressToken = $progressToken;
        $this->progress = $progress;
        $this->total = $total;
        $this->message = $message;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|null,
     *     progressToken: string|number,
     *     progress: float,
     *     total?: int|null,
     *     message?: string|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['progressToken', 'progress']);

        /** @var string|number $progressToken */
        $progressToken = self::asStringOrNumber($data['progressToken']);

        return new self(
            $progressToken,
            self::asFloat($data['progress']),
            self::asArrayOrNull($data['_meta'] ?? null),
            self::asIntOrNull($data['total'] ?? null),
            self::asStringOrNull($data['message'] ?? null)
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

        $result['progressToken'] = $this->progressToken;
        $result['progress'] = $this->progress;
        if ($this->total !== null) {
            $result['total'] = $this->total;
        }
        if ($this->message !== null) {
            $result['message'] = $this->message;
        }

        return $result;
    }

    /**
     * @return string|number
     */
    public function getProgressToken()
    {
        return $this->progressToken;
    }

    /**
     * @return float
     */
    public function getProgress(): float
    {
        return $this->progress;
    }

    /**
     * @return int|null
     */
    public function getTotal(): ?int
    {
        return $this->total;
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }
}
