# PHP MCP Schema

A PHP representation of the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) schema types.

This package provides Data Transfer Objects (DTOs), Enums, and Unions that mirror the official MCP TypeScript schema.
It is **not** an SDK, client, or server implementation;
just the type definitions for building your own MCP-compatible applications in PHP.

## Installation

```bash
composer require wordpress/php-mcp-schema
```

Requires PHP 7.4 or higher.

## Usage

### Creating a Tool Definition

```php
use WP\McpSchema\Server\Tools\DTO\Tool;

$tool = Tool::fromArray([
    'name' => 'get_weather',
    'description' => 'Get current weather for a location',
    'inputSchema' => [
        'type' => 'object',
        'properties' => [
            'location' => ['type' => 'string', 'description' => 'City name'],
        ],
        'required' => ['location'],
    ],
]);
```

### Serialization (toArray)

Convert a DTO to a plain array for JSON encoding:

```php
use WP\McpSchema\Server\Tools\DTO\Tool;

$tool = Tool::fromArray([
    'name' => 'get_weather',
    'description' => 'Get current weather for a location',
    'inputSchema' => ['type' => 'object', 'properties' => []],
]);

$array = $tool->toArray();
$json  = json_encode($array); // Ready to send over the wire
```

### Deserialization (fromArray)

Decode incoming JSON into a fully typed DTO:

```php
use WP\McpSchema\Server\Tools\DTO\CallToolRequest;

$json = '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"get_weather","arguments":{"location":"Paris"}}}';
$data = json_decode($json, true);

$request   = CallToolRequest::fromArray($data);
$tool_name = $request->getTypedParams()->getName(); // "get_weather"
$arguments = $request->getTypedParams()->getArguments(); // ['location' => 'Paris']
```

### Factory / Union Types

Use a factory to resolve polymorphic content blocks without knowing the concrete type up front:

```php
use WP\McpSchema\Common\Protocol\Factory\ContentBlockFactory;
use WP\McpSchema\Common\Content\DTO\TextContent;

$block = ContentBlockFactory::fromArray(['type' => 'text', 'text' => 'Hello, world!']);

// $block implements ContentBlockInterface; cast when you need the concrete API
if ($block instanceof TextContent) {
    echo $block->getText(); // "Hello, world!"
}
```

### JSON-RPC Messages

Construct a generic JSON-RPC request for any MCP method:

```php
use WP\McpSchema\Common\JsonRpc\DTO\JSONRPCRequest;

$request = JSONRPCRequest::fromArray([
    'jsonrpc' => '2.0',
    'id'      => 1,
    'method'  => 'tools/list',
]);

$json = json_encode($request->toArray());
```

## Available Types

### Server Types (`WP\McpSchema\Server\`)

- **Tools** - `Tool`, `CallToolRequest`, `CallToolResult`, `ListToolsRequest`, `ListToolsResult`
- **Resources** - `Resource`, `ResourceTemplate`, `ReadResourceRequest`, `ReadResourceResult`
- **Prompts** - `Prompt`, `PromptMessage`, `GetPromptRequest`, `GetPromptResult`
- **Logging** - `LoggingMessageNotification`, `SetLevelRequest`

### Client Types (`WP\McpSchema\Client\`)

- **Sampling** - `CreateMessageRequest`, `CreateMessageResult`, `SamplingMessage`
- **Elicitation** - `ElicitRequest`, `ElicitResult`
- **Roots** - `ListRootsRequest`, `ListRootsResult`, `Root`

### Common Types (`WP\McpSchema\Common\`)

- **Protocol** - `InitializeRequest`, `InitializeResult`, `PingRequest`
- **Content** - `TextContent`, `ImageContent`, `AudioContent`
- **JSON-RPC** - `JSONRPCRequest`, `JSONRPCNotification`, `JSONRPCResultResponse`, `JSONRPCErrorResponse`

## Generator

The PHP code in `src/` is auto-generated from the official MCP TypeScript schema. The generator is located in the `generator/` directory and is not included in the Composer package.

See [generator/README.md](generator/README.md) for setup and usage instructions.

## License

GPL-2.0-or-later - see [LICENSE.md](LICENSE.md) for details.

## Links

- [Model Context Protocol Specification](https://spec.modelcontextprotocol.io/)
- [MCP GitHub Repository](https://github.com/modelcontextprotocol/modelcontextprotocol)
