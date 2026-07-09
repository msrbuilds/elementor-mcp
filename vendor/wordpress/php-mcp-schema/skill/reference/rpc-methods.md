# RPC Methods Reference

## Client → Server

| Method | Direction | Request | Result |
| --- | --- | --- | --- |
| `initialize` | client→server | InitializeRequest | InitializeResult |
| `resources/list` | client→server | ListResourcesRequest | ListResourcesResult |
| `resources/templates/list` | client→server | ListResourceTemplatesRequest | ListResourceTemplatesResult |
| `resources/read` | client→server | ReadResourceRequest | ReadResourceResult |
| `resources/subscribe` | client→server | SubscribeRequest | Result |
| `resources/unsubscribe` | client→server | UnsubscribeRequest | Result |
| `prompts/list` | client→server | ListPromptsRequest | ListPromptsResult |
| `prompts/get` | client→server | GetPromptRequest | GetPromptResult |
| `tools/list` | client→server | ListToolsRequest | ListToolsResult |
| `tools/call` | client→server | CallToolRequest | CallToolResult |
| `logging/setLevel` | client→server | SetLevelRequest | Result |
| `completion/complete` | client→server | CompleteRequest | CompleteResult |

## Server → Client

| Method | Direction | Request | Result |
| --- | --- | --- | --- |
| `sampling/createMessage` | server→client | CreateMessageRequest | CreateMessageResult |
| `roots/list` | server→client | ListRootsRequest | ListRootsResult |
| `elicitation/create` | server→client | ElicitRequest | ElicitResult |

## Bidirectional

| Method | Direction | Request | Result |
| --- | --- | --- | --- |
| `ping` | bidirectional | PingRequest | Result |
| `tasks/get` | bidirectional | GetTaskRequest | Result |
| `tasks/result` | bidirectional | GetTaskPayloadRequest | GetTaskPayloadResult |
| `tasks/cancel` | bidirectional | CancelTaskRequest | Result |
| `tasks/list` | bidirectional | ListTasksRequest | ListTasksResult |
