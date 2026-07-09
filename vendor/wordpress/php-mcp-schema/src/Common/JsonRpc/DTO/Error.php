<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\JsonRpc\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * @since 2025-11-25
 *
 * @mcp-domain Common
 * @mcp-subdomain JsonRpc
 * @mcp-version 2025-11-25
 */
class Error extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * The error type that occurred.
     *
     * @since 2025-11-25
     *
     * @var int
     */
    protected int $code;

    /**
     * A short description of the error. The message SHOULD be limited to a concise single sentence.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $message;

    /**
     * Additional information about the error. The value of this member is defined by the sender (e.g. detailed error information, nested errors etc.).
     *
     * @since 2025-11-25
     *
     * @var mixed|null
     */
    protected $data;

    /**
     * @param int $code @since 2025-11-25
     * @param string $message @since 2025-11-25
     * @param mixed|null $data @since 2025-11-25
     */
    public function __construct(
        int $code,
        string $message,
        $data = null
    ) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     code: int,
     *     message: string,
     *     data?: mixed|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['code', 'message']);

        return new self(
            self::asInt($data['code']),
            self::asString($data['message']),
            $data['data'] ?? null
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

        $result['code'] = $this->code;
        $result['message'] = $this->message;
        if ($this->data !== null) {
            $result['data'] = $this->data;
        }

        return $result;
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return mixed|null
     */
    public function getData()
    {
        return $this->data;
    }
}
