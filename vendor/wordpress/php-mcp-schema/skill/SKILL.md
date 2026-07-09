---
name: mcp-php-schema
description: Navigate and understand the MCP PHP schema. Use when implementing MCP clients/servers, understanding protocol types, or finding the right DTO for a task.
---

# MCP PHP Schema Reference

## Quick Navigation

- **Server types** (resources, tools, prompts): [reference/server.md](reference/server.md)
- **Client types** (sampling, elicitation, roots): [reference/client.md](reference/client.md)
- **Common types** (protocol, JSON-RPC): [reference/common.md](reference/common.md)
- **RPC methods**: [reference/rpc-methods.md](reference/rpc-methods.md)
- **Factories**: [reference/factories.md](reference/factories.md)

## Schema Structure

3 domains, 17 subdomains, 164 types total.

### Domains Overview

| Domain | Types | Purpose |
| --- | --- | --- |
| Common | 59 | Protocol base, JSON-RPC, content blocks |
| Server | 59 | Tools, resources, prompts, logging |
| Client | 46 | Sampling, elicitation, roots, tasks |

## Common Patterns

### Finding a Request/Result Pair

1. Check [rpc-methods.md](reference/rpc-methods.md) for method name
2. Look up request type in domain file
3. Find corresponding result type

### Using Factory Classes

Factories create the correct DTO from discriminator values.
See [factories.md](reference/factories.md) for patterns.

## JSON Data Files

For programmatic access:

- `data/schema-index.json` - Lightweight discovery index
- `data/schema-common.json` - Common domain types
- `data/schema-server.json` - Server domain types
- `data/schema-client.json` - Client domain types

## Search Scripts

```bash
# Search types by name
./scripts/search-types.sh "Resource"

# Get type details
./scripts/get-type.sh "CallToolRequest"

# Find RPC method
./scripts/find-rpc.sh "tools/call"
```
