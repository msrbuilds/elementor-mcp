<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Protocol\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\NotificationParams;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Parameters for a `notifications/cancelled` notification.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Common
 * @mcp-subdomain Protocol
 * @mcp-version 2025-11-25
 */
class CancelledNotificationParams extends NotificationParams
{
    use ValidatesRequiredFields;

    /**
     * The ID of the request to cancel.
     *
     * This MUST correspond to the ID of a request previously issued in the same direction.
     * This MUST be provided for cancelling non-task requests.
     * This MUST NOT be used for cancelling tasks (use the `tasks/cancel` request instead).
     *
     * @since 2025-11-25
     *
     * @var string|number|null
     */
    protected $requestId;

    /**
     * An optional string describing the reason for the cancellation. This MAY be logged or presented to the user.
     *
     * @since 2025-11-25
     *
     * @var string|null
     */
    protected ?string $reason;

    /**
     * @param array<string, mixed>|null $_meta @since 2025-11-25
     * @param string|number|null $requestId @since 2025-11-25
     * @param string|null $reason @since 2025-11-25
     */
    public function __construct(
        ?array $_meta = null,
        $requestId = null,
        ?string $reason = null
    ) {
        parent::__construct($_meta);
        $this->requestId = $requestId;
        $this->reason = $reason;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|null,
     *     requestId?: string|number|null,
     *     reason?: string|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        /** @var string|number|null $requestId */
        $requestId = isset($data['requestId'])
            ? self::asStringOrNumberOrNull($data['requestId'])
            : null;

        return new self(
            self::asArrayOrNull($data['_meta'] ?? null),
            $requestId,
            self::asStringOrNull($data['reason'] ?? null)
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

        if ($this->requestId !== null) {
            $result['requestId'] = $this->requestId;
        }
        if ($this->reason !== null) {
            $result['reason'] = $this->reason;
        }

        return $result;
    }

    /**
     * @return string|number|null
     */
    public function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * @return string|null
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }
}
