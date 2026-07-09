<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\JsonRpc\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * See [General fields: `_meta`](/specification/2025-11-25/basic/index#meta) for notes on `_meta` usage.
 *
 * @mcp-domain Common
 * @mcp-subdomain JsonRpc
 * @mcp-version 2025-11-25
 */
class RequestParamsMeta extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * If specified, the caller is requesting out-of-band progress notifications for this request (as represented by notifications/progress). The value of this parameter is an opaque token that will be attached to any subsequent notifications. The receiver is not obligated to provide these notifications.
     *
     * @var string|number|null
     */
    protected $progressToken;

    /**
     * @param string|number|null $progressToken
     */
    public function __construct(
        $progressToken = null
    ) {
        $this->progressToken = $progressToken;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     progressToken?: string|number|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        /** @var string|number|null $progressToken */
        $progressToken = isset($data['progressToken'])
            ? self::asStringOrNumberOrNull($data['progressToken'])
            : null;

        return new self(
            $progressToken
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

        if ($this->progressToken !== null) {
            $result['progressToken'] = $this->progressToken;
        }

        return $result;
    }

    /**
     * @return string|number|null
     */
    public function getProgressToken()
    {
        return $this->progressToken;
    }
}
