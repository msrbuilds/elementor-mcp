# Factory Classes Reference

Factories instantiate the correct DTO based on discriminator values.

## Common Factories

### SamplingMessageContentBlockFactory

- **Interface:** `SamplingMessageContentBlockInterface`
- **Discriminator:** `type`

**Mappings:**

| Value | Type |
| --- | --- |
| `text` | TextContent |
| `image` | ImageContent |
| `audio` | AudioContent |
| `tool_use` | ToolUseContent |
| `tool_result` | ToolResultContent |

### ContentBlockFactory

- **Interface:** `ContentBlockInterface`
- **Discriminator:** `type`

**Mappings:**

| Value | Type |
| --- | --- |
| `text` | TextContent |
| `image` | ImageContent |
| `audio` | AudioContent |
| `resource_link` | ResourceLink |
| `resource` | EmbeddedResource |

### ClientRequestFactory

- **Interface:** `ClientRequestInterface`
- **Discriminator:** `method`

**Mappings:**

| Value | Type |
| --- | --- |
| `ping` | PingRequest |
| `initialize` | InitializeRequest |
| `completion/complete` | CompleteRequest |
| `logging/setLevel` | SetLevelRequest |
| `prompts/get` | GetPromptRequest |
| `prompts/list` | ListPromptsRequest |
| `resources/list` | ListResourcesRequest |
| `resources/templates/list` | ListResourceTemplatesRequest |
| `resources/read` | ReadResourceRequest |
| `resources/subscribe` | SubscribeRequest |
| `resources/unsubscribe` | UnsubscribeRequest |
| `tools/call` | CallToolRequest |
| `tools/list` | ListToolsRequest |
| `tasks/get` | GetTaskRequest |
| `tasks/result` | GetTaskPayloadRequest |
| `tasks/list` | ListTasksRequest |
| `tasks/cancel` | CancelTaskRequest |

### ClientNotificationFactory

- **Interface:** `ClientNotificationInterface`
- **Discriminator:** `method`

**Mappings:**

| Value | Type |
| --- | --- |
| `notifications/cancelled` | CancelledNotification |
| `notifications/progress` | ProgressNotification |
| `notifications/initialized` | InitializedNotification |
| `notifications/roots/list_changed` | RootsListChangedNotification |
| `notifications/tasks/status` | TaskStatusNotification |

### ServerRequestFactory

- **Interface:** `ServerRequestInterface`
- **Discriminator:** `method`

**Mappings:**

| Value | Type |
| --- | --- |
| `ping` | PingRequest |
| `sampling/createMessage` | CreateMessageRequest |
| `roots/list` | ListRootsRequest |
| `elicitation/create` | ElicitRequest |
| `tasks/get` | GetTaskRequest |
| `tasks/result` | GetTaskPayloadRequest |
| `tasks/list` | ListTasksRequest |
| `tasks/cancel` | CancelTaskRequest |


## Client Factories

### ElicitRequestParamsFactory

- **Interface:** `ElicitRequestParamsInterface`
- **Discriminator:** `mode`

**Mappings:**

| Value | Type |
| --- | --- |
| `form` | ElicitRequestFormParams |
| `url` | ElicitRequestURLParams |

### PrimitiveSchemaDefinitionFactory

- **Interface:** `PrimitiveSchemaDefinitionInterface`
- **Discriminator:** `type`

**Mappings:**

| Value | Type |
| --- | --- |
| `string` | StringSchema |
| `number" \| "integer` | NumberSchema |
| `boolean` | BooleanSchema |

### SingleSelectEnumSchemaFactory

- **Interface:** `SingleSelectEnumSchemaInterface`
- **Discriminator:** `type`

**Mappings:**

| Value | Type |
| --- | --- |
| `string` | TitledSingleSelectEnumSchema |

### MultiSelectEnumSchemaFactory

- **Interface:** `MultiSelectEnumSchemaInterface`
- **Discriminator:** `type`

**Mappings:**

| Value | Type |
| --- | --- |
| `array` | TitledMultiSelectEnumSchema |

### EnumSchemaFactory

- **Interface:** `EnumSchemaInterface`
- **Discriminator:** `type`

**Mappings:**

| Value | Type |
| --- | --- |
| `string` | LegacyTitledEnumSchema |


## Server Factories

### ServerNotificationFactory

- **Interface:** `ServerNotificationInterface`
- **Discriminator:** `method`

**Mappings:**

| Value | Type |
| --- | --- |
| `notifications/cancelled` | CancelledNotification |
| `notifications/progress` | ProgressNotification |
| `notifications/message` | LoggingMessageNotification |
| `notifications/resources/updated` | ResourceUpdatedNotification |
| `notifications/resources/list_changed` | ResourceListChangedNotification |
| `notifications/tools/list_changed` | ToolListChangedNotification |
| `notifications/prompts/list_changed` | PromptListChangedNotification |
| `notifications/elicitation/complete` | ElicitationCompleteNotification |
| `notifications/tasks/status` | TaskStatusNotification |
