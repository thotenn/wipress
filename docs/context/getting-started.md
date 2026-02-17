# Getting Started

## Installation

1. Copy the `wipress/` folder into `wp-content/plugins/`
2. Activate "WiPress" in `wp-admin > Plugins`
3. The `wiki` post type is now available

> **SELinux/Podman note**: If copying files while the container is running, recreate containers so the `:Z` flag relabels files:
> ```bash
> podman compose down && podman compose up -d
> ```

## Creating Your First Wiki

### 1. Create a Project

Projects group related documentation. Create one from any wiki page's editor:

1. Go to `wp-admin > Wikis > Add New`
2. In the right sidebar, find **Proyectos** (Projects)
3. Click "Add Category" and type your project name (e.g., "My App")

### 2. Create a Section

Sections are tabs within a project (e.g., "Docs", "API", "Changelog"):

1. In the same editor sidebar, find **Secciones** (Sections)
2. Click "Add Category" and type a section name (e.g., "Docs")

### 3. Create Pages

#### Root Page (Folder)

Create a page that serves as a container:

1. Title: "Getting Started"
2. Leave content **empty** (this makes it a folder)
3. Assign project and section
4. Set **Page Order** to `0` in the "Page Order" panel
5. Publish

#### Child Pages

Create pages under the folder:

1. Title: "Installation"
2. In the sidebar, set **Parent** to "Getting Started"
3. Set **Page Order** to `1`
4. Add content (HTML blocks or the Markdown block)
5. Publish

#### Leaf Pages with Markdown

1. Title: "For Mac Users"
2. Set Parent to "Installation", Order to `0`
3. Click **+** in the editor, search for **"Markdown"**
4. Write your Markdown content
5. Toggle **Preview** to see rendered output
6. Publish

### 4. View Your Wiki

Visit any wiki page on the frontend. You'll see:

- **Header** with project name and section tabs
- **Left sidebar** with search bar and collapsible page tree
- **Content** area with your page (code blocks have a copy button)
- **Right sidebar** with table of contents (from headings)

## Example Structure

After setup, your wiki might look like:

```
My App (project)
└── Docs (section)
    ├── Getting Started (folder, order: 0)
    │   ├── Installation (folder, order: 1)
    │   │   ├── For Mac Users (order: 0)
    │   │   ├── For Linux Users (order: 1)
    │   │   └── For Windows Users (order: 2)
    │   ├── Configuration (order: 2)
    │   └── Troubleshooting (order: 3)
    └── FAQ (order: 10)
```

## Using the REST API

### Quick Test

```bash
# List projects (no auth needed)
curl http://localhost:5580/wp-json/wipress/v1/projects

# Get project tree
curl http://localhost:5580/wp-json/wipress/v1/projects/my-app/tree

# Search
curl "http://localhost:5580/wp-json/wipress/v1/search?q=install"
```

### Create Content via API

First, create an Application Password:

1. Go to `wp-admin > Users > Your Profile`
2. Under **Application Passwords**, enter a name and click "Add New"
3. Copy the password

```bash
# Create a page
curl -X POST http://localhost:5580/wp-json/wipress/v1/pages \
  -u "admin:xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Deployment Guide",
    "content": "## Deploy\n\n1. Build\n2. Ship",
    "content_format": "markdown",
    "project": "my-app",
    "section": "Docs",
    "parent": 16,
    "menu_order": 4
  }'
```

See [rest-api.md](rest-api.md) for full endpoint documentation.

## Using the MCP Server

### Connect an AI Assistant

For read-only access to a specific project (no auth needed):

```json
{
  "mcpServers": {
    "my-app-docs": {
      "url": "http://localhost:5580/wp-json/wipress/v1/mcp/my-app"
    }
  }
}
```

For full read/write access to all projects:

```json
{
  "mcpServers": {
    "wipress": {
      "url": "http://localhost:5580/wp-json/wipress/v1/mcp",
      "headers": {
        "Authorization": "Basic dXNlcm5hbWU6YXBwX3Bhc3N3b3Jk"
      }
    }
  }
}
```

(The Authorization value is `base64("username:app_password")`)

The project-scoped endpoint (`/mcp/my-app`) automatically filters all tools to that project — the LLM doesn't need to specify `project` in any tool call.

### Test Manually

```bash
# Initialize (global)
curl -X POST http://localhost:5580/wp-json/wipress/v1/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'

# Initialize (scoped to a project)
curl -X POST http://localhost:5580/wp-json/wipress/v1/mcp/my-app \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'

# List tools
curl -X POST http://localhost:5580/wp-json/wipress/v1/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'
```

See [mcp-server.md](mcp-server.md) for full tool documentation.

## Tips

- **Folder pages**: Leave content empty for pages that only serve as containers
- **Menu order**: Use gaps (0, 10, 20) to make it easy to insert pages later
- **Markdown block**: Use Write/Preview toggle to check rendering before publishing
- **Permalinks**: If URLs show `?wiki=slug`, enable pretty permalinks in `wp-admin > Settings > Permalinks`
- **Cache busting**: If CSS/JS changes don't appear, hard-refresh with Ctrl+Shift+R
