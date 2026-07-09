<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Contracts;

/**
 * Interface for types that extend JSONRPCRequest.
 *
 * This interface is auto-generated from TypeScript extends metadata.
 * Types implementing this interface: InitializeRequest, PingRequest, PaginatedRequest, ListResourcesRequest, ListResourceTemplatesRequest, ReadResourceRequest, SubscribeRequest, UnsubscribeRequest, ListPromptsRequest, GetPromptRequest, ListToolsRequest, CallToolRequest, GetTaskRequest, GetTaskPayloadRequest, CancelTaskRequest, ListTasksRequest, SetLevelRequest, CreateMessageRequest, CompleteRequest, ListRootsRequest, ElicitRequest
 *
 * @mcp-version 2025-11-25
 */
interface JSONRPCRequestInterface extends WithArrayTransformationInterface
{
}
