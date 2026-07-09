<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\DTO;

use WP\McpSchema\Client\Elicitation\Union\ElicitRequestParamsInterface;
use WP\McpSchema\Client\Tasks\DTO\TaskMetadata;
use WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta;
use WP\McpSchema\Common\Tasks\DTO\TaskAugmentedRequestParams;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * The parameters for a request to elicit information from the user via a URL in the client.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
class ElicitRequestURLParams extends TaskAugmentedRequestParams implements ElicitRequestParamsInterface
{
    use ValidatesRequiredFields;

    public const MODE = 'url';

    public const DISCRIMINATOR_FIELD = 'mode';
    public const DISCRIMINATOR_VALUE = 'url';

    /**
     * The elicitation mode.
     *
     * @since 2025-11-25
     *
     * @var 'url'
     */
    protected string $mode;

    /**
     * The message to present to the user explaining why the interaction is needed.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $message;

    /**
     * The ID of the elicitation, which must be unique within the context of the server.
     * The client MUST treat this ID as an opaque value.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $elicitationId;

    /**
     * The URL that the user should navigate to.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $url;

    /**
     * @param string $message @since 2025-11-25
     * @param string $elicitationId @since 2025-11-25
     * @param string $url @since 2025-11-25
     * @param \WP\McpSchema\Client\Tasks\DTO\TaskMetadata|null $task @since 2025-11-25
     * @param \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta @since 2025-11-25
     */
    public function __construct(
        string $message,
        string $elicitationId,
        string $url,
        ?TaskMetadata $task = null,
        ?RequestParamsMeta $_meta = null
    ) {
        parent::__construct($_meta, $task);
        $this->mode = self::MODE;
        $this->message = $message;
        $this->elicitationId = $elicitationId;
        $this->url = $url;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     task?: array<string, mixed>|\WP\McpSchema\Client\Tasks\DTO\TaskMetadata|null,
     *     _meta?: array<string, mixed>|\WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null,
     *     mode: 'url',
     *     message: string,
     *     elicitationId: string,
     *     url: string
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['message', 'elicitationId', 'url']);

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

        return new self(
            self::asString($data['message']),
            self::asString($data['elicitationId']),
            self::asString($data['url']),
            $task,
            $_meta
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

        $result['mode'] = $this->mode;
        $result['message'] = $this->message;
        $result['elicitationId'] = $this->elicitationId;
        $result['url'] = $this->url;

        return $result;
    }

    /**
     * @return 'url'
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return string
     */
    public function getElicitationId(): string
    {
        return $this->elicitationId;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }
}
