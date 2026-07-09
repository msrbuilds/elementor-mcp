<?php

declare(strict_types=1);

namespace WP\McpSchema\Client\Elicitation\DTO;

use WP\McpSchema\Client\Elicitation\Union\ElicitRequestParamsInterface;
use WP\McpSchema\Client\Tasks\DTO\TaskMetadata;
use WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta;
use WP\McpSchema\Common\Tasks\DTO\TaskAugmentedRequestParams;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * The parameters for a request to elicit non-sensitive information from the user via a form in the client.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Client
 * @mcp-subdomain Elicitation
 * @mcp-version 2025-11-25
 */
class ElicitRequestFormParams extends TaskAugmentedRequestParams implements ElicitRequestParamsInterface
{
    use ValidatesRequiredFields;

    public const MODE = 'form';

    public const DISCRIMINATOR_FIELD = 'mode';
    public const DISCRIMINATOR_VALUE = 'form';

    /**
     * The elicitation mode.
     *
     * @since 2025-11-25
     *
     * @var 'form'|null
     */
    protected ?string $mode;

    /**
     * The message to present to the user describing what information is being requested.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $message;

    /**
     * A restricted subset of JSON Schema.
     * Only top-level properties are allowed, without nesting.
     *
     * @since 2025-11-25
     *
     * @var \WP\McpSchema\Client\Elicitation\DTO\ElicitRequestFormParamsRequestedSchema
     */
    protected ElicitRequestFormParamsRequestedSchema $requestedSchema;

    /**
     * @param string $message @since 2025-11-25
     * @param \WP\McpSchema\Client\Elicitation\DTO\ElicitRequestFormParamsRequestedSchema $requestedSchema @since 2025-11-25
     * @param \WP\McpSchema\Client\Tasks\DTO\TaskMetadata|null $task @since 2025-11-25
     * @param \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta @since 2025-11-25
     */
    public function __construct(
        string $message,
        ElicitRequestFormParamsRequestedSchema $requestedSchema,
        ?TaskMetadata $task = null,
        ?RequestParamsMeta $_meta = null
    ) {
        parent::__construct($_meta, $task);
        $this->mode = self::MODE;
        $this->message = $message;
        $this->requestedSchema = $requestedSchema;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     task?: array<string, mixed>|\WP\McpSchema\Client\Tasks\DTO\TaskMetadata|null,
     *     _meta?: array<string, mixed>|\WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null,
     *     mode?: 'form'|null,
     *     message: string,
     *     requestedSchema: array<string, mixed>|\WP\McpSchema\Client\Elicitation\DTO\ElicitRequestFormParamsRequestedSchema
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['message', 'requestedSchema']);

        /** @var \WP\McpSchema\Client\Elicitation\DTO\ElicitRequestFormParamsRequestedSchema $requestedSchema */
        $requestedSchema = is_array($data['requestedSchema'])
            ? ElicitRequestFormParamsRequestedSchema::fromArray(self::asArray($data['requestedSchema']))
            : $data['requestedSchema'];

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
            $requestedSchema,
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

        if ($this->mode !== null) {
            $result['mode'] = $this->mode;
        }
        $result['message'] = $this->message;
        $result['requestedSchema'] = $this->requestedSchema->toArray();

        return $result;
    }

    /**
     * @return 'form'|null
     */
    public function getMode(): ?string
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
     * @return \WP\McpSchema\Client\Elicitation\DTO\ElicitRequestFormParamsRequestedSchema
     */
    public function getRequestedSchema(): ElicitRequestFormParamsRequestedSchema
    {
        return $this->requestedSchema;
    }
}
