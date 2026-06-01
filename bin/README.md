# @msrbuilds/emcp-proxy

stdio↔HTTP proxy that connects MCP clients (Claude Desktop, Claude Code, Cursor, etc.) to a **remote** WordPress site running the [MCP Tools for Elementor (EMCP)](https://emcp.msrbuilds.com) plugin.

MCP clients like Claude Desktop only speak the **stdio** transport and launch their servers as a local subprocess. This proxy runs locally, accepts JSON-RPC over stdio, and forwards it to your WordPress site's MCP HTTP endpoint — handling authentication, the `Mcp-Session-Id` session lifecycle, and pretty/plain permalink detection for you.

> Because the client launches it locally, the proxy must run on the **same machine as your MCP client**, not on the WordPress server. `npx` is the easiest way to do that without keeping a local copy in sync.

## Usage (Claude Desktop)

Add to `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "elementor-mcp": {
      "command": "npx",
      "args": ["-y", "@msrbuilds/emcp-proxy@latest"],
      "env": {
        "WP_URL": "https://your-site.com",
        "WP_USERNAME": "admin",
        "WP_APP_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx",
        "MCP_PROTOCOL_VERSION": "2024-11-05"
      }
    }
  }
}
```

Create the application password at **WordPress Admin → Users → Profile → Application Passwords**.

## Environment variables

| Variable | Required | Purpose |
|---|---|---|
| `WP_URL` | yes | WordPress site URL, e.g. `https://your-site.com` |
| `WP_USERNAME` | yes | WordPress username |
| `WP_APP_PASSWORD` | yes | WordPress Application Password |
| `MCP_PROTOCOL_VERSION` | no | Override the protocol version in the `initialize` handshake. Set to `2024-11-05` if your client rejects the adapter's `2025-06-18`. |
| `MCP_LOG_FILE` | no | Path to a debug log file. |

## Requirements

- Node.js 18+ on the machine running the MCP client
- WordPress with the MCP Tools for Elementor plugin active, and an Application Password

## License

GPL-2.0-or-later
