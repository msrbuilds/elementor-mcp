# Common Domain Types

## Contents

- [Content](#content) (3 types)
- [Core](#core) (1 types)
- [JsonRpc](#jsonrpc) (13 types)
- [Lifecycle](#lifecycle) (1 types)
- [Protocol](#protocol) (31 types)
- [Tasks](#tasks) (10 types)

## Content

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| AudioContent | Audio provided to or from an LLM | type: "audio", data: string, mimeType: string, +2 more |
| ImageContent | An image provided to or from an LLM | type: "image", data: string, mimeType: string, +2 more |
| TextContent | Text provided to or from an LLM | type: "text", text: string, annotations?: Annotations, +1 more |

### Relationships

- `TextContent` implements `SamplingMessageContentBlockInterface`
- `TextContent` implements `ContentBlockInterface`
- `ImageContent` implements `SamplingMessageContentBlockInterface`
- `ImageContent` implements `ContentBlockInterface`
- `AudioContent` implements `SamplingMessageContentBlockInterface`

## Core

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| Icon | An optionally-sized icon that can be displayed in a user ... | src: string, mimeType?: string, sizes?: string[], +1 more |

## JsonRpc

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| Error | Error data structure | code: number, message: string, data?: unknown |
| JSONRPCErrorResponse | A response to a request that indicates an error occurred | jsonrpc: typeof JSONR..., id?: RequestId, error: Error |
| JSONRPCMessageInterface | Refers to any valid JSON-RPC object that can be decoded o... | - |
| JSONRPCNotification | A notification which does not expect a response | jsonrpc: typeof JSONR... |
| JSONRPCRequest | A request that expects a response | jsonrpc: typeof JSONR..., id: RequestId |
| JSONRPCResponseInterface | A response to a request, containing either the result or ... | - |
| JSONRPCResultResponse | A successful (non-error) response to a request | jsonrpc: typeof JSONR..., id: RequestId, result: Result |
| Notification | Notification for  events | method: string, params?: { [key: stri... |
| NotificationParams | Parameters for Notification | _meta?: { [key: stri... |
| Request | Request for  operation | method: string, params?: { [key: stri... |
| RequestIdInterface | A uniquely identifying ID for a request in JSON-RPC | - |
| RequestParams | Common params for any request | _meta?: RequestParam... |
| RequestParamsMeta | See [General fields: `_meta`](/specification/2025-11-25/b... | progressToken?: ProgressToken |

### Relationships

- `JSONRPCRequest` extends `Request`
- `JSONRPCRequest` implements `JSONRPCMessageInterface`
- `JSONRPCNotification` extends `Notification`
- `JSONRPCNotification` implements `JSONRPCMessageInterface`
- `JSONRPCResultResponse` implements `JSONRPCResponseInterface`

## Lifecycle

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| Implementation | Describes the MCP implementation | version: string, description?: string, websiteUrl?: string |

### Relationships

- `Implementation` extends `BaseMetadata`

## Protocol

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| Annotations | Optional annotations for the client | audience?: Role[], priority?: number, lastModified?: string |
| BaseMetadata | Base interface for metadata with name (identifier) and ti... | name: string, title?: string |
| BlobResourceContents | Blob Resource Contents data structure | blob: string |
| CancelledNotification | This notification can be sent by either side to indicate ... | method: "notificatio..., params: CancelledNot... |
| CancelledNotificationParams | Parameters for a `notifications/cancelled` notification | requestId?: RequestId, reason?: string |
| ClientNotificationInterface | Union type: CancelledNotification \| ProgressNotification ... | - |
| ClientRequestInterface | Union type: PingRequest \| InitializeRequest \| CompleteReq... | - |
| ContentBlockInterface | Union type: TextContent \| ImageContent \| AudioContent \| R... | - |
| EmbeddedResource | The contents of a resource, embedded into a prompt or too... | type: "resource", resource: TextResource..., annotations?: Annotations, +1 more |
| EmptyResult | A response that indicates success but carries no data | - |
| GetTaskPayloadRequest | A request to retrieve the result of a completed task | method: "tasks/result", params: GetTaskPaylo... |
| GetTaskPayloadRequestParams | Parameters for GetTaskPayloadRequest | taskId: string |
| GetTaskPayloadResult | The response to a tasks/result request | - |
| Icons | Base interface to add `icons` property | icons?: Icon[] |
| InitializedNotification | This notification is sent from the client to the server a... | method: "notificatio..., params?: Notification... |
| InitializeRequest | This request is sent from the client to the server when i... | method: "initialize", params: InitializeRe... |
| InitializeRequestParams | Parameters for an `initialize` request | protocolVersion: string, capabilities: ClientCapabi..., clientInfo: Implementation |
| InitializeResult | After receiving an initialize request from the client, th... | protocolVersion: string, capabilities: ServerCapabi..., serverInfo: Implementation, +1 more |
| PaginatedRequest | Request for Paginated operation | params?: PaginatedReq... |
| PaginatedRequestParams | Common parameters for paginated requests | cursor?: Cursor |
| PaginatedResult | Result from Paginated operation | nextCursor?: Cursor |
| PingRequest | A ping, issued by either the server or the client, to che... | method: "ping", params?: RequestParams |
| ProgressNotification | An out-of-band notification used to inform the receiver o... | method: "notificatio..., params: ProgressNoti... |
| ProgressNotificationParams | Parameters for a `notifications/progress` notification | progressToken: ProgressToken, progress: number, total?: number, +1 more |
| ProgressTokenInterface | A progress token, used to associate progress notification... | - |
| Result | Result from  operation | _meta?: { [key: stri... |
| Role | The sender or recipient of messages and data in a convers... | USER: user, ASSISTANT: assistant |
| SamplingMessageContentBlockInterface | Union type: TextContent \| ImageContent \| AudioContent \| T... | - |
| ServerRequestInterface | Union type: PingRequest \| CreateMessageRequest \| ListRoot... | - |
| TextResourceContents | Text Resource Contents data structure | text: string |
| URLElicitationRequiredError | An error response that indicates that the server requires... | error: Error & {
  ... |

### Relationships

- `URLElicitationRequiredError` extends `Omit`
- `CancelledNotificationParams` extends `NotificationParams`
- `CancelledNotification` extends `JSONRPCNotification`
- `CancelledNotification` implements `ClientNotificationInterface`
- `CancelledNotification` implements `ServerNotificationInterface`

## Tasks

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| CancelTaskRequest | A request to cancel a task | method: "tasks/cancel", params: CancelTaskRe... |
| CancelTaskRequestParams | Parameters for CancelTaskRequest | taskId: string |
| CancelTaskResult | The response to a tasks/cancel request | taskId: string, status: "working" \| ..., statusMessage?: string, +4 more |
| GetTaskRequest | A request to retrieve the state of a task | method: "tasks/get", params: GetTaskReque... |
| GetTaskRequestParams | Parameters for GetTaskRequest | taskId: string |
| GetTaskResult | The response to a tasks/get request | taskId: string, status: "working" \| ..., statusMessage?: string, +4 more |
| ListTasksRequest | A request to retrieve a list of tasks | method: "tasks/list" |
| ListTasksResult | The response to a tasks/list request | tasks: Task[] |
| TaskAugmentedRequestParams | Common params for any task-augmented request | task?: TaskMetadata |
| TaskStatusNotification | An optional notification from the receiver to the request... | method: "notificatio..., params: TaskStatusNo... |

### Relationships

- `TaskAugmentedRequestParams` extends `RequestParams`
- `GetTaskRequest` extends `JSONRPCRequest`
- `GetTaskRequest` implements `ClientRequestInterface`
- `GetTaskRequest` implements `ServerRequestInterface`
- `CancelTaskRequest` extends `JSONRPCRequest`
