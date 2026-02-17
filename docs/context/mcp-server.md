# MCP Server

## What is MCP?

The [Model Context Protocol](https://modelcontextprotocol.io/) (MCP) is an open protocol that allows AI assistants (like Claude, ChatGPT, etc.) to interact with external tools and data sources. WiPress implements an MCP server so LLMs can read, create, update, and manage wiki content.

## Endpoint

```
POST /wp-json/wipress/v1/mcp
```

Or with query parameter:

```
POST /?rest_route=/wipress/v1/mcp
```

## Protocol

- **Transport**: Streamable HTTP
- **Format**: JSON-RPC 2.0
- **Protocol Version**: `2024-11-05`

Every request must include:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "method_name",
  "params": {}
}
```

## Authentication

- **Read tools** (`wiki_list_projects`, `wiki_get_tree`, `wiki_get_page`, `wiki_search`, etc.) — no authentication required
- **Write tools** (`wiki_create_page`, `wiki_update_page`, `wiki_delete_page`, `wiki_move_page`) — require WordPress Application Passwords via Basic Auth

```bash
curl -X POST http://localhost:5580/wp-json/wipress/v1/mcp \
  -u "username:xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{...}}'
```

## Lifecycle

### 1. Initialize

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "initialize",
  "params": {}
}
```

**Response:**
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "protocolVersion": "2024-11-05",
    "capabilities": { "tools": {} },
    "serverInfo": { "name": "wipress", "version": "1.0.1" }
  }
}
```

### 2. List Tools

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "tools/list",
  "params": {}
}
```

Returns all 10 tools with names, descriptions, and JSON Schema input definitions.

### 3. Call a Tool

```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "method": "tools/call",
  "params": {
    "name": "tool_name",
    "arguments": { ... }
  }
}
```

### 4. List Resources

```json
{
  "jsonrpc": "2.0",
  "id": 4,
  "method": "resources/list",
  "params": {}
}
```

### 5. Read a Resource

```json
{
  "jsonrpc": "2.0",
  "id": 5,
  "method": "resources/read",
  "params": { "uri": "wiki://project/my-project" }
}
```

## Tools Reference

### `wiki_list_projects`

List all wiki projects.

**Arguments:** none

**Example:**
```json
{"name": "wiki_list_projects", "arguments": {}}
```

### `wiki_list_sections`

List sections, optionally filtered by project.

| Argument | Type | Required | Description |
|----------|------|----------|-------------|
| `project` | string | no | Project slug to filter by |

**Example:**
```json
{"name": "wiki_list_sections", "arguments": {"project": "my-project"}}
```

### `wiki_get_tree`

Get the full navigation tree for a project, organized by sections with nested page hierarchy.

| Argument | Type | Required | Description |
|----------|------|----------|-------------|
| `project` | string | yes | Project slug |

**Example:**
```json
{"name": "wiki_get_tree", "arguments": {"project": "my-project"}}
```

### `wiki_list_pages`

List wiki pages with optional filters.

| Argument | Type | Required | Description |
|----------|------|----------|-------------|
| `project` | string | no | Filter by project slug |
| `section` | string | no | Filter by section slug |
| `parent` | integer | no | Filter by parent page ID |
| `search` | string | no | Search query |

**Example:**
```json
{"name": "wiki_list_pages", "arguments": {"project": "my-project", "section": "docs"}}
```

### `wiki_get_page`

Get a wiki page by ID with full content.

| Argument | Type | Required | Description |
|----------|------|----------|-------------|
| `id` | integer | yes | Page ID |

**Example:**
```json
{"name": "wiki_get_page", "arguments": {"id": 26}}
```

### `wiki_create_page` (auth required)

Create a new wiki page.

| Argument | Type | Required | Description |
|----------|------|----------|-------------|
| `title` | string | yes | Page title |
| `content` | string | yes | Content (Markdown or HTML) |
| `content_format` | string | no | `"markdown"` or `"html"` |
| `project` | string | no | Project slug |
| `section` | string | no | Section name |
| `parent` | integer | no | Parent page ID |
| `menu_order` | integer | no | Sort order |

**Example:**
```json
{
  "name": "wiki_create_page",
  "arguments": {
    "title": "Quick Start",
    "content": "## Getting Started\n\nRun `npm install`...",
    "content_format": "markdown",
    "project": "my-project",
    "section": "Docs",
    "parent": 16,
    "menu_order": 0
  }
}
```

### `wiki_update_page` (auth required)

Update an existing wiki page. Only include fields to change.

| Argument | Type | Required | Description |
|----------|------|----------|-------------|
| `id` | integer | yes | Page ID |
| `title` | string | no | New title |
| `content` | string | no | New content |
| `content_format` | string | no | `"markdown"` or `"html"` |

**Example:**
```json
{"name": "wiki_update_page", "arguments": {"id": 26, "title": "Updated Title"}}
```

### `wiki_delete_page` (auth required)

Permanently delete a wiki page.

| Argument | Type | Required | Description |
|----------|------|----------|-------------|
| `id` | integer | yes | Page ID to delete |

### `wiki_move_page` (auth required)

Move a page to a new parent or change its sort order.

| Argument | Type | Required | Description |
|----------|------|----------|-------------|
| `id` | integer | yes | Page ID |
| `parent` | integer | no | New parent page ID (0 for root) |
| `menu_order` | integer | no | New sort order |

### `wiki_search`

Search wiki content with contextual excerpts.

| Argument | Type | Required | Description |
|----------|------|----------|-------------|
| `query` | string | yes | Search query |
| `project` | string | no | Limit search to project slug |

## Resources

WiPress exposes each project as a resource:

- **URI pattern**: `wiki://project/{slug}`
- **MIME type**: `application/json`
- **Content**: Full navigation tree (same as `wiki_get_tree`)

Resources are listed dynamically based on existing projects.

## Error Handling

### Tool Errors

When a tool fails, the response includes `isError: true`:

```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "result": {
    "content": [{"type": "text", "text": "Error: Authentication required for write operations"}],
    "isError": true
  }
}
```

### Protocol Errors

Invalid requests return JSON-RPC errors:

```json
{
  "jsonrpc": "2.0",
  "id": null,
  "error": {"code": -32600, "message": "Invalid JSON-RPC 2.0 request"}
}
```

| Code | Meaning |
|------|---------|
| -32600 | Invalid request |
| -32601 | Method not found |
| -32602 | Invalid params |

## Configuring an MCP Client

To connect an AI assistant to WiPress, configure it as an MCP server:

```json
{
  "mcpServers": {
    "wipress": {
      "url": "http://localhost:5580/wp-json/wipress/v1/mcp",
      "headers": {
        "Authorization": "Basic base64(username:app_password)"
      }
    }
  }
}
```

## Typical AI Workflow

1. **Explore**: Call `wiki_list_projects` → `wiki_get_tree` to understand the structure
2. **Read**: Call `wiki_get_page` to read specific content
3. **Search**: Call `wiki_search` to find relevant pages
4. **Write**: Call `wiki_create_page` or `wiki_update_page` to add/modify content
5. **Organize**: Call `wiki_move_page` to restructure the tree
