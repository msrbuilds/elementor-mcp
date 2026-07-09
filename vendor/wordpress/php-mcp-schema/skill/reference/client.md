# Client Domain Types

## Contents

- [Elicitation](#elicitation) (22 types)
- [Lifecycle](#lifecycle) (6 types)
- [Roots](#roots) (4 types)
- [Sampling](#sampling) (9 types)
- [Tasks](#tasks) (5 types)

## Elicitation

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| BooleanSchema | Boolean Schema data structure | type: "boolean", title?: string, description?: string, +1 more |
| ElicitationCompleteNotification | An optional notification from the server to the client, i... | method: "notificatio..., params: ElicitationC... |
| ElicitationCompleteNotificationParams | Parameters for ElicitationCompleteNotification | elicitationId: string |
| ElicitRequest | A request from the server to elicit additional informatio... | method: "elicitation..., params: ElicitReques... |
| ElicitRequestFormParams | The parameters for a request to elicit non-sensitive info... | mode?: "form", message: string, requestedSchema: ElicitReques... |
| ElicitRequestFormParamsRequestedSchema | A restricted subset of JSON Schema | $schema?: string, type: "object", required?: string[] |
| ElicitRequestParamsInterface | The parameters for a request to elicit additional informa... | - |
| ElicitRequestURLParams | The parameters for a request to elicit information from t... | mode: "url", message: string, elicitationId: string, +1 more |
| ElicitResult | The client's response to an elicitation request | action: "accept" \| "..., content?: { [key: stri... |
| EnumSchemaInterface | Union type: SingleSelectEnumSchema \| MultiSelectEnumSchem... | - |
| LegacyTitledEnumSchema | Use TitledSingleSelectEnumSchema instead | type: "string", title?: string, description?: string, +3 more |
| MultiSelectEnumSchemaInterface | Union type: UntitledMultiSelectEnumSchema \| TitledMultiSe... | - |
| NumberSchema | Number Schema data structure | type: "number" \| "..., title?: string, description?: string, +3 more |
| PrimitiveSchemaDefinitionInterface | Restricted schema definitions that only allow primitive t... | - |
| SingleSelectEnumSchemaInterface | Union type: UntitledSingleSelectEnumSchema \| TitledSingle... | - |
| StringSchema | String Schema data structure | type: "string", title?: string, description?: string, +4 more |
| TitledMultiSelectEnumSchema | Schema for multiple-selection enumeration with display ti... | type: "array", title?: string, description?: string, +4 more |
| TitledMultiSelectEnumSchemaItems | Schema for array items with enum options and display labels | - |
| TitledSingleSelectEnumSchema | Schema for single-selection enumeration with display titl... | type: "string", title?: string, description?: string, +2 more |
| UntitledMultiSelectEnumSchema | Schema for multiple-selection enumeration without display... | type: "array", title?: string, description?: string, +4 more |
| UntitledMultiSelectEnumSchemaItems | Schema for the array items | type: "string", enum: string[] |
| UntitledSingleSelectEnumSchema | Schema for single-selection enumeration without display t... | type: "string", title?: string, description?: string, +2 more |

### Relationships

- `ElicitRequestFormParams` extends `TaskAugmentedRequestParams`
- `ElicitRequestFormParams` implements `ElicitRequestParamsInterface`
- `ElicitRequestURLParams` extends `TaskAugmentedRequestParams`
- `ElicitRequestURLParams` implements `ElicitRequestParamsInterface`
- `ElicitRequest` extends `JSONRPCRequest`

## Lifecycle

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| ClientCapabilities | Capabilities a client may support | experimental?: { [key: stri..., roots?: ClientCapabi..., sampling?: ClientCapabi..., +2 more |
| ClientCapabilitiesElicitation | Present if the client supports elicitation from the server | form?: object, url?: object |
| ClientCapabilitiesRoots | Present if the client supports listing roots | listChanged?: boolean |
| ClientCapabilitiesSampling | Present if the client supports sampling from an LLM | context?: object, tools?: object |
| ClientCapabilitiesTasks | Present if the client supports task-augmented requests | list?: object, cancel?: object |
| ClientResultInterface | Union type: EmptyResult \| CreateMessageResult \| ListRoots... | - |

## Roots

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| ListRootsRequest | Sent from the server to request a list of root URIs from ... | method: "roots/list", params?: RequestParams |
| ListRootsResult | The client's response to a roots/list request from the se... | roots: Root[] |
| Root | Represents a root directory or file that the server can o... | uri: string, name?: string, _meta?: { [key: stri... |
| RootsListChangedNotification | A notification from the client to the server, informing i... | method: "notificatio..., params?: Notification... |

### Relationships

- `ListRootsRequest` extends `JSONRPCRequest`
- `ListRootsRequest` implements `ServerRequestInterface`
- `ListRootsResult` extends `Result`
- `ListRootsResult` implements `ClientResultInterface`
- `RootsListChangedNotification` extends `JSONRPCNotification`

## Sampling

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| CreateMessageRequest | A request from the server to sample an LLM via the client | method: "sampling/cr..., params: CreateMessag... |
| CreateMessageRequestParams | Parameters for a `sampling/createMessage` request | messages: SamplingMess..., modelPreferences?: ModelPrefere..., systemPrompt?: string, +7 more |
| CreateMessageResult | The client's response to a sampling/createMessage request... | model: string, stopReason?: "endTurn" \| ... |
| ModelHint | Hints to use for model selection | name?: string |
| ModelPreferences | The server's preferences for model selection, requested o... | hints?: ModelHint[], costPriority?: number, speedPriority?: number, +1 more |
| SamplingMessage | Describes a message issued to or received from an LLM API | role: Role, content: SamplingMess..., _meta?: { [key: stri... |
| ToolChoice | Controls tool selection behavior for sampling requests | mode?: "auto" \| "re... |
| ToolResultContent | The result of a tool use, provided by the user back to th... | type: "tool_result", toolUseId: string, content: ContentBlock[], +3 more |
| ToolUseContent | A request from the assistant to call a tool | type: "tool_use", id: string, name: string, +2 more |

### Relationships

- `CreateMessageRequestParams` extends `TaskAugmentedRequestParams`
- `CreateMessageRequest` extends `JSONRPCRequest`
- `CreateMessageRequest` implements `ServerRequestInterface`
- `CreateMessageResult` extends `Result`
- `CreateMessageResult` implements `ClientResultInterface`

## Tasks

### Types

| Type | Purpose | Key Properties |
| --- | --- | --- |
| CreateTaskResult | A response to a task-augmented request | task: Task |
| RelatedTaskMetadata | Metadata for associating messages with a task | taskId: string |
| Task | Data associated with a task | taskId: string, status: TaskStatus, statusMessage?: string, +4 more |
| TaskMetadata | Metadata for augmenting a request with task execution | ttl?: number |
| TaskStatusInterface | The status of a task | - |

### Relationships

- `CreateTaskResult` extends `Result`
