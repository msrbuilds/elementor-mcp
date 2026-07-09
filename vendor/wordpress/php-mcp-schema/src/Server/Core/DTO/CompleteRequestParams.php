<?php

declare(strict_types=1);

namespace WP\McpSchema\Server\Core\DTO;

use WP\McpSchema\Common\JsonRpc\DTO\RequestParams;
use WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * Parameters for a `completion/complete` request.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Server
 * @mcp-subdomain Core
 * @mcp-version 2025-11-25
 */
class CompleteRequestParams extends RequestParams
{
    use ValidatesRequiredFields;

    /**
     * @since 2025-11-25
     *
     * @var \WP\McpSchema\Server\Core\DTO\PromptReference|\WP\McpSchema\Server\Core\DTO\ResourceTemplateReference
     */
    protected $ref;

    /**
     * The argument's information
     *
     * @since 2025-11-25
     *
     * @var \WP\McpSchema\Server\Core\DTO\CompleteRequestParamsArgument
     */
    protected CompleteRequestParamsArgument $argument;

    /**
     * Additional, optional context for completions
     *
     * @since 2025-11-25
     *
     * @var \WP\McpSchema\Server\Core\DTO\CompleteRequestParamsContext|null
     */
    protected ?CompleteRequestParamsContext $context;

    /**
     * @param \WP\McpSchema\Server\Core\DTO\PromptReference|\WP\McpSchema\Server\Core\DTO\ResourceTemplateReference $ref @since 2025-11-25
     * @param \WP\McpSchema\Server\Core\DTO\CompleteRequestParamsArgument $argument @since 2025-11-25
     * @param \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta @since 2025-11-25
     * @param \WP\McpSchema\Server\Core\DTO\CompleteRequestParamsContext|null $context @since 2025-11-25
     */
    public function __construct(
        $ref,
        CompleteRequestParamsArgument $argument,
        ?RequestParamsMeta $_meta = null,
        ?CompleteRequestParamsContext $context = null
    ) {
        parent::__construct($_meta);
        $this->ref = $ref;
        $this->argument = $argument;
        $this->context = $context;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     _meta?: array<string, mixed>|\WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null,
     *     ref: \WP\McpSchema\Server\Core\DTO\PromptReference|\WP\McpSchema\Server\Core\DTO\ResourceTemplateReference,
     *     argument: array<string, mixed>|\WP\McpSchema\Server\Core\DTO\CompleteRequestParamsArgument,
     *     context?: array<string, mixed>|\WP\McpSchema\Server\Core\DTO\CompleteRequestParamsContext|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['ref', 'argument']);

        /** @var \WP\McpSchema\Server\Core\DTO\PromptReference|\WP\McpSchema\Server\Core\DTO\ResourceTemplateReference $ref */
        $ref = $data['ref'];

        /** @var \WP\McpSchema\Server\Core\DTO\CompleteRequestParamsArgument $argument */
        $argument = is_array($data['argument'])
            ? CompleteRequestParamsArgument::fromArray(self::asArray($data['argument']))
            : $data['argument'];

        /** @var \WP\McpSchema\Common\JsonRpc\DTO\RequestParamsMeta|null $_meta */
        $_meta = isset($data['_meta'])
            ? (is_array($data['_meta'])
                ? RequestParamsMeta::fromArray(self::asArray($data['_meta']))
                : $data['_meta'])
            : null;

        /** @var \WP\McpSchema\Server\Core\DTO\CompleteRequestParamsContext|null $context */
        $context = isset($data['context'])
            ? (is_array($data['context'])
                ? CompleteRequestParamsContext::fromArray(self::asArray($data['context']))
                : $data['context'])
            : null;

        return new self(
            $ref,
            $argument,
            $_meta,
            $context
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

        $result['ref'] = (is_object($this->ref) && method_exists($this->ref, 'toArray')) ? $this->ref->toArray() : $this->ref;
        $result['argument'] = $this->argument->toArray();
        if ($this->context !== null) {
            $result['context'] = $this->context->toArray();
        }

        return $result;
    }

    /**
     * @return \WP\McpSchema\Server\Core\DTO\PromptReference|\WP\McpSchema\Server\Core\DTO\ResourceTemplateReference
     */
    public function getRef()
    {
        return $this->ref;
    }

    /**
     * @return \WP\McpSchema\Server\Core\DTO\CompleteRequestParamsArgument
     */
    public function getArgument(): CompleteRequestParamsArgument
    {
        return $this->argument;
    }

    /**
     * @return \WP\McpSchema\Server\Core\DTO\CompleteRequestParamsContext|null
     */
    public function getContext(): ?CompleteRequestParamsContext
    {
        return $this->context;
    }
}
