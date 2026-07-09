# Server Domain Types

## Contents

- [Core](#core) (8 types)
- [Lifecycle](#lifecycle) (7 types)
- [Logging](#logging) (5 types)
- [Prompts](#prompts) (9 types)
- [Resources](#resources) (19 types)
- [Tools](#tools) (11 types)

## Core

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| CompleteRequest | A request from the client to the server, to ask for compl... | method: "completion/..., params: CompleteRequ... |
| CompleteRequestParams | Parameters for a `completion/complete` request | ref: PromptRefere..., argument: CompleteRequ..., context?: CompleteRequ... |
| CompleteRequestParamsArgument | The argument's information | name: string, value: string |
| CompleteRequestParamsContext | Additional, optional context for completions | arguments?: { [key: stri... |
| CompleteResult | The server's response to a completion/complete request | completion: CompleteResu... |
| CompleteResultCompletion | Complete Result Completion data structure | values: string[], total?: number, hasMore?: boolean |
| PromptReference | Identifies a prompt | type: "ref/prompt" |
| ResourceTemplateReference | A reference to a resource or resource template definition | type: "ref/resource", uri: string |

### Relationships

- `CompleteRequestParams` extends `RequestParams`
- `CompleteRequest` extends `JSONRPCRequest`
- `CompleteRequest` implements `ClientRequestInterface`
- `CompleteResult` extends `Result`
- `CompleteResult` implements `ServerResultInterface`

## Lifecycle

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| ServerCapabilities | Capabilities that a server may support | experimental?: { [key: stri..., logging?: object, completions?: object, +4 more |
| ServerCapabilitiesPrompts | Present if the server offers any prompt templates | listChanged?: boolean |
| ServerCapabilitiesResources | Present if the server offers any resources to read | subscribe?: boolean, listChanged?: boolean |
| ServerCapabilitiesTasks | Present if the server supports task-augmented requests | list?: object, cancel?: object |
| ServerCapabilitiesTools | Present if the server offers any tools to call | listChanged?: boolean |
| ServerNotificationInterface | Union type: CancelledNotification \| ProgressNotification ... | - |
| ServerResultInterface | Union type: EmptyResult \| InitializeResult \| CompleteResu... | - |

## Logging

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| LoggingLevelInterface | The severity of a log message | - |
| LoggingMessageNotification | JSONRPCNotification of a log message passed from server t... | method: "notificatio..., params: LoggingMessa... |
| LoggingMessageNotificationParams | Parameters for a `notifications/message` notification | level: LoggingLevel, logger?: string, data: unknown |
| SetLevelRequest | A request from the client to the server, to enable or adj... | method: "logging/set..., params: SetLevelRequ... |
| SetLevelRequestParams | Parameters for a `logging/setLevel` request | level: LoggingLevel |

### Relationships

- `SetLevelRequestParams` extends `RequestParams`
- `SetLevelRequest` extends `JSONRPCRequest`
- `SetLevelRequest` implements `ClientRequestInterface`
- `LoggingMessageNotificationParams` extends `NotificationParams`
- `LoggingMessageNotification` extends `JSONRPCNotification`

## Prompts

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| GetPromptRequest | Used by the client to get a prompt provided by the server | method: "prompts/get", params: GetPromptReq... |
| GetPromptRequestParams | Parameters for a `prompts/get` request | name: string, arguments?: { [key: stri... |
| GetPromptResult | The server's response to a prompts/get request from the c... | description?: string, messages: PromptMessage[] |
| ListPromptsRequest | Sent from the client to request a list of prompts and pro... | method: "prompts/list" |
| ListPromptsResult | The server's response to a prompts/list request from the ... | prompts: Prompt[] |
| Prompt | A prompt or prompt template that the server offers | description?: string, arguments?: PromptArgume..., _meta?: { [key: stri... |
| PromptArgument | Describes an argument that a prompt can accept | description?: string, required?: boolean |
| PromptListChangedNotification | An optional notification from the server to the client, i... | method: "notificatio..., params?: Notification... |
| PromptMessage | Describes a message returned as part of a prompt | role: Role, content: ContentBlock |

### Relationships

- `ListPromptsRequest` extends `PaginatedRequest`
- `ListPromptsRequest` implements `ClientRequestInterface`
- `ListPromptsResult` extends `PaginatedResult`
- `ListPromptsResult` implements `ServerResultInterface`
- `GetPromptRequestParams` extends `RequestParams`

## Resources

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| ListResourcesRequest | Sent from the client to request a list of resources the s... | method: "resources/l... |
| ListResourcesResult | The server's response to a resources/list request from th... | resources: Resource[] |
| ListResourceTemplatesRequest | Sent from the client to request a list of resource templa... | method: "resources/t... |
| ListResourceTemplatesResult | The server's response to a resources/templates/list reque... | resourceTemplates: ResourceTemp... |
| ReadResourceRequest | Sent from the client to the server, to read a specific re... | method: "resources/r..., params: ReadResource... |
| ReadResourceRequestParams | Parameters for a `resources/read` request | - |
| ReadResourceResult | The server's response to a resources/read request from th... | contents: (TextResourc... |
| Resource | A known resource that the server is capable of reading | uri: string, description?: string, mimeType?: string, +3 more |
| ResourceContents | The contents of a specific resource or sub-resource | uri: string, mimeType?: string, _meta?: { [key: stri... |
| ResourceLink | A resource that the server is capable of reading, include... | type: "resource_link" |
| ResourceListChangedNotification | An optional notification from the server to the client, i... | method: "notificatio..., params?: Notification... |
| ResourceRequestParams | Common parameters when working with resources | uri: string |
| ResourceTemplate | A template description for resources available on the server | uriTemplate: string, description?: string, mimeType?: string, +2 more |
| ResourceUpdatedNotification | A notification from the server to the client, informing i... | method: "notificatio..., params: ResourceUpda... |
| ResourceUpdatedNotificationParams | Parameters for a `notifications/resources/updated` notifi... | uri: string |
| SubscribeRequest | Sent from the client to request resources/updated notific... | method: "resources/s..., params: SubscribeReq... |
| SubscribeRequestParams | Parameters for a `resources/subscribe` request | - |
| UnsubscribeRequest | Sent from the client to request cancellation of resources... | method: "resources/u..., params: UnsubscribeR... |
| UnsubscribeRequestParams | Parameters for a `resources/unsubscribe` request | - |

### Relationships

- `ListResourcesRequest` extends `PaginatedRequest`
- `ListResourcesRequest` implements `ClientRequestInterface`
- `ListResourcesResult` extends `PaginatedResult`
- `ListResourcesResult` implements `ServerResultInterface`
- `ListResourceTemplatesRequest` extends `PaginatedRequest`

## Tools

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| CallToolRequest | Used by the client to invoke a tool provided by the server | method: "tools/call", params: CallToolRequ... |
| CallToolRequestParams | Parameters for a `tools/call` request | name: string, arguments?: { [key: stri... |
| CallToolResult | The server's response to a tool call | content: ContentBlock[], structuredContent?: { [key: stri..., isError?: boolean |
| ListToolsRequest | Sent from the client to request a list of tools the serve... | method: "tools/list" |
| ListToolsResult | The server's response to a tools/list request from the cl... | tools: Tool[] |
| Tool | Definition for a tool the client can call | description?: string, inputSchema: ToolInputSchema, execution?: ToolExecution, +3 more |
| ToolAnnotations | Additional properties describing a Tool to clients | title?: string, readOnlyHint?: boolean, destructiveHint?: boolean, +2 more |
| ToolExecution | Execution-related properties for a tool | taskSupport?: "forbidden" ... |
| ToolInputSchema | A JSON Schema object defining the expected parameters for... | $schema?: string, type: "object", properties?: { [key: stri..., +1 more |
| ToolListChangedNotification | An optional notification from the server to the client, i... | method: "notificatio..., params?: Notification... |
| ToolOutputSchema | An optional JSON Schema object defining the structure of ... | $schema?: string, type: "object", properties?: { [key: stri..., +1 more |

### Relationships

- `ListToolsRequest` extends `PaginatedRequest`
- `ListToolsRequest` implements `ClientRequestInterface`
- `ListToolsResult` extends `PaginatedResult`
- `ListToolsResult` implements `ServerResultInterface`
- `CallToolResult` extends `Result`
