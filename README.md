# WiPress

A wiki system for WordPress with hierarchical navigation, Markdown support, REST API, and MCP server for AI integration.

## Features

- **Hierarchical pages** — nest pages under parents to build a documentation tree
- **Collapsible sidebar** — Tabby-style navigation with expand/collapse, auto-expands only the active branch
- **Sidebar search** — search bar above the tree with debounced REST API calls, dropdown results, keyboard support
- **Folder pages** — pages without content act as containers that toggle their children
- **Table of Contents** — auto-generated from headings with active section tracking via IntersectionObserver
- **Code copy button** — hover-to-reveal copy button on all code blocks
- **Markdown block** — Gutenberg block with Write/Preview toggle, no build step required
- **Projects & Sections** — organize docs by project (e.g., "My App") and section (e.g., "Docs", "API")
- **Private projects** — toggle visibility per project; private projects only accessible to editors and administrators
- **REST API** — full CRUD, tree navigation, search, and move operations
- **MCP Server** — JSON-RPC 2.0 endpoint so AI assistants can read and write wiki content
- **Dark mode** — automatic via `prefers-color-scheme`
- **Responsive** — 3-column layout collapses gracefully on smaller screens
- **Zero build tools** — no npm, no webpack, no compilation. Just PHP, JS, and CSS.

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

1. Copy the `wipress/` folder to `wp-content/plugins/`
2. Activate **WiPress** in the WordPress admin
3. Start creating wiki pages under **Wikis** in the admin menu

> **Podman/SELinux**: if you copy files while the container is running, recreate containers so the `:Z` flag relabels everything:
> ```bash
> podman compose down && podman compose up -d
> ```

## Quick Start

### 1. Create a project structure

In the block editor for any wiki page:

- Assign a **Project** (e.g., "My App") in the sidebar taxonomy panel
- Assign a **Section** (e.g., "Docs")
- Set a **Parent** page to create hierarchy
- Set **Page Order** for sort position (lower = first)

### 2. Write content

Use standard WordPress blocks or the **Markdown** block:

1. Click **+** in the editor
2. Search for **"Markdown"**
3. Write Markdown in the editor, toggle **Preview** to check

### 3. View your wiki

Visit any wiki page on the frontend. The layout includes:

- Sticky header with project name and section tabs
- Left sidebar with search bar and collapsible page tree
- Main content area with code copy buttons on code blocks
- Right sidebar with table of contents

### Example structure

```
Getting Started              (folder, no content)
  Installation               (folder)
    For Mac Users            (page with content)
    For Linux Users          (page with content)
    For Windows Users        (page with content)
  Configuration              (page with content)
  Troubleshooting            (page with content)
```

## REST API

Base: `/wp-json/wipress/v1`

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/projects` | No | List all projects |
| GET | `/projects/{slug}/tree` | No | Full navigation tree |
| GET | `/pages` | No | List pages (filters: project, section, parent, search) |
| GET | `/pages/{id}` | No | Get page with content |
| POST | `/pages` | Yes | Create page |
| PUT | `/pages/{id}` | Yes | Update page |
| DELETE | `/pages/{id}` | Yes | Delete page |
| PATCH | `/pages/{id}/move` | Yes | Move page (change parent/order) |
| GET | `/search?q=term` | No | Full-text search |
| POST | `/render-markdown` | Yes | Preview Markdown as HTML |

Authentication uses WordPress Application Passwords (Basic Auth). Create one at `wp-admin > Users > Profile > Application Passwords`.

```bash
# List projects
curl http://localhost:5580/wp-json/wipress/v1/projects

# Get navigation tree
curl http://localhost:5580/wp-json/wipress/v1/projects/my-app/tree

# Create a page
curl -X POST http://localhost:5580/wp-json/wipress/v1/pages \
  -u "admin:xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Deployment",
    "content": "## Deploy\n\n1. Build\n2. Ship",
    "content_format": "markdown",
    "project": "my-app",
    "section": "Docs",
    "parent": 16,
    "menu_order": 5
  }'

# Search
curl "http://localhost:5580/wp-json/wipress/v1/search?q=install"
```

## MCP Server

WiPress exposes an MCP endpoint for AI assistants at:

```
POST /wp-json/wipress/v1/mcp
```

### Available tools

| Tool | Description |
|------|-------------|
| `wiki_list_projects` | List all projects |
| `wiki_list_sections` | List sections (optional project filter) |
| `wiki_get_tree` | Full navigation tree for a project |
| `wiki_list_pages` | List pages with filters |
| `wiki_get_page` | Get page content by ID |
| `wiki_create_page` | Create a new page |
| `wiki_update_page` | Update an existing page |
| `wiki_delete_page` | Delete a page |
| `wiki_move_page` | Move/reorder a page |
| `wiki_search` | Search wiki content |

### Connect an AI assistant

```json
{
  "mcpServers": {
    "wipress": {
      "url": "http://localhost:5580/wp-json/wipress/v1/mcp",
      "headers": {
        "Authorization": "Basic base64(user:app_password)"
      }
    }
  }
}
```

### Test manually

```bash
# Initialize
curl -X POST http://localhost:5580/wp-json/wipress/v1/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'

# List tools
curl -X POST http://localhost:5580/wp-json/wipress/v1/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'

# Call a tool
curl -X POST http://localhost:5580/wp-json/wipress/v1/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"wiki_get_tree","arguments":{"project":"my-app"}}}'
```

## File Structure

```
wipress/
  wipress.php                    # Bootstrap
  includes/
    class-post-type.php          # CPT + taxonomies
    class-walker-wiki.php        # Sidebar tree walker
    class-template.php           # Template loading + helpers
    class-markdown.php           # Parsedown wrapper
    class-rest-api.php           # REST API (450 lines)
    class-mcp-server.php         # MCP server (306 lines)
  vendor/
    Parsedown.php                # Parsedown 1.7.4 (MIT)
  blocks/markdown/
    block.json                   # Block metadata
    index.js                     # Editor (no build step)
    index.asset.php              # Dependencies
    render.php                   # Server-side render
    editor.css                   # Editor styles
  templates/
    single-wiki.php              # Frontend template
  assets/
    style.css                    # Frontend styles
    script.js                    # Search, code copy, TOC, sidebar toggle
    editor-order-panel.js        # Menu Order panel
  docs/context/
    overview.md                  # Architecture & concepts
    hierarchical-navigation.md   # Sidebar, walker, TOC
    markdown.md                  # Markdown support
    rest-api.md                  # API reference
    mcp-server.md                # MCP reference
    getting-started.md           # Setup guide
```

## Technical Details

- **~1,800 lines** of code total (PHP + JS + CSS)
- Parsedown 1.7.4 bundled (MIT license, single file)
- HTML sanitized via `wp_kses_post()` — blocks XSS while allowing safe tags
- Application Passwords enabled over HTTP for local development
- Legacy endpoint `/wiki-devhub/v1/publish` maintained for backward compatibility
- MCP protocol version: `2024-11-05`

## License

GPL-2.0-or-later (same as WordPress)
