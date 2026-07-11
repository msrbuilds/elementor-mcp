# @msrbuilds/emcp-proxy

stdio↔HTTP proxy that connects MCP clients (Claude Desktop, Claude Code, Cursor, etc.) to a **remote** WordPress site running the [MCP Tools for Elementor (EMCP)](https://emcptools.com) plugin.

MCP clients like Claude Desktop only speak the **stdio** transport and launch their servers as a local subprocess. This proxy runs locally, accepts JSON-RPC over stdio, and forwards it to your WordPress site's MCP HTTP endpoint — handling authentication, the `Mcp-Session-Id` session lifecycle, and pretty/plain permalink detection for you.

> Because the client launches it locally, the proxy must run on the **same machine as your MCP client**, not on the WordPress server. `npx` is the easiest way to do that without keeping a local copy in sync.

## Usage (Claude Desktop)

Add to `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "emcp-tools": {
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

## Multiple sites (one session, many installs)

Instead of the single `WP_URL` set, provide a **site registry** and drive several WordPress installs from one connection. Set `EMCP_SITES` to a JSON map of aliases → credentials (or point `EMCP_SITES_FILE` at a JSON file with the same shape):

```json
{
  "mcpServers": {
    "emcp-tools": {
      "command": "npx",
      "args": ["-y", "@msrbuilds/emcp-proxy@latest"],
      "env": {
        "EMCP_SITES": "{\"acme\":{\"url\":\"https://acme.com\",\"username\":\"admin\",\"appPassword\":\"xxxx xxxx xxxx xxxx\"},\"globex\":{\"url\":\"https://globex.com\",\"username\":\"admin\",\"appPassword\":\"yyyy yyyy yyyy yyyy\"}}",
        "EMCP_DEFAULT_SITE": "acme"
      }
    }
  }
}
```

When more than one site is configured, two extra tools appear:

- **`emcp_list_sites`** — list the configured sites and which one is active.
- **`emcp_use_site`** — switch the active site (`{ "site": "globex" }`); every subsequent tool call targets it. The proxy keeps a separate session per site and initializes each one transparently on first use.

Single-site `WP_URL` mode is unchanged and does **not** add these tools.

## Environment variables

| Variable | Required | Purpose |
|---|---|---|
| `WP_URL` | single-site | WordPress site URL, e.g. `https://your-site.com` |
| `WP_USERNAME` | single-site | WordPress username |
| `WP_APP_PASSWORD` | single-site | WordPress Application Password |
| `EMCP_SITES` | multi-site | JSON registry: `{ "alias": { "url", "username", "appPassword" }, … }` |
| `EMCP_SITES_FILE` | multi-site | Path to a JSON file with the registry (alternative to `EMCP_SITES`) |
| `EMCP_DEFAULT_SITE` | no | Alias to start on (defaults to the first entry) |
| `MCP_PROTOCOL_VERSION` | no | Override the protocol version in the `initialize` handshake. Set to `2024-11-05` if your client rejects the adapter's `2025-06-18`. |
| `MCP_LOG_FILE` | no | Path to a debug log file. |

## Requirements

- Node.js 18+ on the machine running the MCP client
- WordPress with the MCP Tools for Elementor plugin active, and an Application Password

## License

GPL-2.0-or-later
