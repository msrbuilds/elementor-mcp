<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
class ElicitationCompleteNotificationParams extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * The ID of the elicitation that completed.
     *
     * @var string
     */
    protected string $elicitationId;

    /**
     * @param string $elicitationId
     */
    public function __construct(
        string $elicitationId
    ) {
        $this->elicitationId = $elicitationId;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     elicitationId: string
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['elicitationId']);

        return new self(
            self::asString($data['elicitationId'])
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

        $result['elicitationId'] = $this->elicitationId;

        return $result;
    }

    /**
     * @return string
     */
    public function getElicitationId(): string
    {
        return $this->elicitationId;
    }
}
