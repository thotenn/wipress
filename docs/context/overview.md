# WiPress - Wiki System for WordPress

## What is WiPress?

WiPress is a WordPress plugin that turns your site into a documentation wiki with hierarchical navigation, Markdown support, a full REST API, and an MCP server for AI/LLM integration. The visual reference is [Tabby](https://tabby.tabbyml.com/docs) — a clean, collapsible sidebar, sticky table of contents, and project/section organization.

## Architecture

WiPress is built on WordPress's native systems:

- **Custom Post Type** (`wiki`) — hierarchical, so pages can have parents and children
- **Two taxonomies** — `wiki_project` (groups of docs, with optional private visibility) and `wiki_section` (tabs within a project like "Docs", "API", "Blog")
- **Walker class** — renders the sidebar tree with expand/collapse, active states, and folder detection
- **Parsedown** — converts Markdown to HTML with WordPress sanitization
- **REST API** — full CRUD + search under `wipress/v1`
- **MCP Server** — JSON-RPC 2.0 endpoint for AI tools to read/write wiki content

## File Structure

```
plugins/wipress/
  wipress.php                          # Bootstrap: constants, requires, init
  includes/
    class-post-type.php                # CPT + taxonomies + rewrite rules + editor panel
    class-walker-wiki.php              # Hierarchical sidebar walker
    class-template.php                 # Template loading + sidebar data helpers
    class-markdown.php                 # Parsedown wrapper + content filter
    class-rest-api.php                 # REST API endpoints + internal methods
    class-mcp-server.php              # MCP server (JSON-RPC 2.0)
  vendor/
    Parsedown.php                      # Parsedown 1.7.4 (MIT, bundled)
  blocks/
    markdown/
      block.json                       # Gutenberg block metadata
      index.js                         # Editor component (no build step)
      index.asset.php                  # Script dependencies
      render.php                       # Server-side render
      editor.css                       # Editor styles
  templates/
    single-wiki.php                    # Single wiki page template
    archive-wiki.php                   # Project listing (/wiki/)
  assets/
    style.css                          # Frontend styles (light/dark)
    script.js                          # Search, code copy, TOC, sidebar toggle, heading anchors, Cmd+K
  build.sh                             # Zip build script for distribution
    editor-order-panel.js              # Menu Order panel for block editor
```

## Key Concepts

### Projects and Sections

Content is organized in two dimensions:

- **Project** — a top-level grouping (e.g., "My App", "Internal Tools")
- **Section** — a tab within a project (e.g., "Docs", "API Reference", "Changelog")

Each wiki page belongs to one project and one section. The frontend header shows the project name and section tabs.

### URL Structure

URLs include the project slug:

- `/wiki/` — archive page listing all projects
- `/wiki/{project-slug}/` — redirects (302) to the project's first page
- `/wiki/{project-slug}/{page-path}/` — individual wiki page

Examples:

```
/wiki/                                          → project listing
/wiki/my-app/                                   → redirect to first page
/wiki/my-app/getting-started/                   → root page
/wiki/my-app/getting-started/installation/      → child page
```

Custom rewrite rules handle the routing. The CPT uses `rewrite => false` and `has_archive => false` to avoid conflicts with WordPress's built-in URL handling.

### Page Hierarchy

Wiki pages are hierarchical — any page can be a parent of other pages. This creates a tree structure visible in the sidebar:

```
Getting Started          (root, folder — no content)
  Installation           (folder)
    For Mac Users        (leaf — has content)
    For Linux Users      (leaf)
    For Windows Users    (leaf)
  Configuration          (leaf)
  Troubleshooting        (leaf)
```

### Folder Pages

A page with children but no content acts as a **folder**. In the sidebar, folders:

- Display as text (not a link) — clicking toggles expand/collapse
- Never navigate away from the current page
- Show a list of child links if accessed directly via URL

### Menu Order

Pages are sorted by `menu_order` (ascending). Set this in the block editor via the **"Page Order"** panel in the sidebar. Lower numbers appear first.

## Content Formats

WiPress supports two content formats:

1. **HTML** (default) — standard WordPress block editor content
2. **Markdown** — raw Markdown stored in a Gutenberg block, rendered server-side via Parsedown

The format is stored in post meta `_wipress_content_format`. When set to `markdown`, the `the_content` filter converts Markdown to HTML before display.

## Frontend Layout

The single wiki template uses a 3-column grid:

| Left Sidebar (260px) | Main Content (fluid) | Right Sidebar (240px) |
|---|---|---|
| Search bar + collapsible page tree | Page title + content | Table of Contents |

- **Header**: sticky, shows project name + section tabs
- **Left sidebar**: sticky, scrollable, search bar at the top + tree navigation with chevron toggles
- **Main content**: code blocks include a hover-to-reveal copy button in the top-right corner
- **Right sidebar**: sticky TOC generated from h2/h3/h4 headings, with active section tracking via IntersectionObserver
- **Responsive**: TOC hides at 1100px, sidebar stacks at 768px; search bar also appears in mobile drawer

## Color Scheme

Automatic light/dark mode via `prefers-color-scheme`:

| Variable | Light | Dark |
|----------|-------|------|
| `--bg` | #ffffff | #030303 |
| `--text` | #1a1a1b | #d7dadc |
| `--accent` | #0079d3 | #4fbcff |
| `--border` | #edeff1 | #343536 |

## Dependencies

- **WordPress 6.0+** (uses block API v3, Walker::has_children property)
- **PHP 7.4+**
- **Parsedown 1.7.4** (bundled, MIT license)
- No npm, no build step, no external JS dependencies
