# WiPress Plugin — Development Context

## What is this?

WiPress is a WordPress wiki plugin located at `plugins/wipress/`. It provides hierarchical documentation pages with a Tabby-style sidebar, Markdown support, REST API, and MCP server.

## Architecture

Single-plugin, class-per-concern, no build step:

```
wipress.php              → bootstrap (defines + requires + init)
includes/
  class-post-type.php    → CPT 'wiki' (hierarchical) + taxonomies + rewrite rules + permalink filter
  class-walker-wiki.php  → extends Walker, renders sidebar tree
  class-template.php     → template loading + sidebar data helpers
  class-markdown.php     → Parsedown wrapper + the_content filter
  class-rest-api.php     → REST endpoints, static *_internal() methods reused by MCP
  class-mcp-server.php   → JSON-RPC 2.0 MCP endpoint, delegates to REST API internals
vendor/Parsedown.php     → Parsedown 1.7.4 bundled (MIT)
blocks/markdown/         → Gutenberg block (no build, uses wp.element directly)
templates/single-wiki.php → frontend 3-column layout
templates/archive-wiki.php → project listing page (/wiki/)
assets/                  → frontend CSS/JS + editor panel JS
```

## Key Design Decisions

- **Walker uses `$this->has_children`** (WordPress 6.x), NOT `$args['has_children']` (pre-6.x)
- **Only ancestors expand** in the sidebar tree — the active item itself stays collapsed
- **Folder detection**: `has_children && empty(trim($post->post_content))` — renders `<span>` toggle instead of `<a>` link
- **Markdown sanitization**: Parsedown with `setMarkupEscaped(false)` + `wp_kses_post()` — allows HTML like `<img>` but blocks `<script>`
- **URL structure**: `/wiki/{project-slug}/{page-path}/` — custom rewrite rules, CPT uses `rewrite => false`
- **Project-scoped MCP**: `/wp-json/wipress/v1/mcp/{project}` — auto-injects project into all tool calls, removes `project` from schemas
- **MCP tools delegate** to `Wipress_REST_API::*_internal()` static methods — no duplicated logic
- **Application Passwords over HTTP** enabled via `wp_is_application_passwords_available` filter for local dev
- **Block has no build step** — `index.js` uses `wp.element.createElement` directly, dependencies declared in `index.asset.php`

## REST API

Namespace: `wipress/v1`

- `GET /projects` — list projects (public)
- `GET /projects/{slug}/tree` — navigation tree (public)
- `GET /pages` — list with filters: project, section, parent, search (public)
- `GET /pages/{id}` — single page with content (public)
- `POST /pages` — create (auth: edit_posts)
- `PUT /pages/{id}` — update (auth)
- `DELETE /pages/{id}` — delete (auth)
- `PATCH /pages/{id}/move` — move parent/order (auth)
- `GET /search?q=` — full-text search (public)
- `POST /render-markdown` — preview markdown (auth)
- `POST /wiki-devhub/v1/publish` — legacy endpoint (auth)

## MCP Server

Endpoints:
- `POST /wipress/v1/mcp` — global (JSON-RPC 2.0), 10 tools
- `POST /wipress/v1/mcp/{project-slug}` — project-scoped, 9 tools (no `wiki_list_projects`, `project` param auto-injected)

Methods: `initialize`, `tools/list`, `tools/call`, `resources/list`, `resources/read`

10 tools: `wiki_list_projects`, `wiki_list_sections`, `wiki_get_tree`, `wiki_list_pages`, `wiki_get_page`, `wiki_create_page`, `wiki_update_page`, `wiki_delete_page`, `wiki_move_page`, `wiki_search`

Write tools require Basic Auth (WordPress Application Passwords). Read tools are public — scoped endpoint can be shared without auth for documentation consumers.

## Conventions

- All classes are prefixed `Wipress_` and use static methods with `::init()` pattern
- Constants: `WIPRESS_VERSION`, `WIPRESS_PATH`, `WIPRESS_URL`
- CSS classes prefixed `wdh-` (legacy from wiki-devhub) for sidebar/layout, `wipress-` for new components (markdown block)
- CSS variables in `:root` with `prefers-color-scheme: dark` media query
- Frontend JS: vanilla JS, no jQuery, no framework
- Editor JS: vanilla `wp.element.createElement`, no JSX, no build

## Post Meta

| Key | Values | Description |
|-----|--------|-------------|
| `_wipress_content_format` | `html`, `markdown` | Content rendering mode |

## Taxonomies

| Taxonomy | Post Type | Purpose |
|----------|-----------|---------|
| `wiki_project` | wiki | Project grouping |
| `wiki_section` | wiki | Section tabs within project |

## When Modifying

- **Adding a REST endpoint**: add route in `register_routes()`, create handler + `*_internal()` static method
- **Adding an MCP tool**: add to `get_tools()` array, add case in `dispatch_tool()`, delegate to REST API internal
- **Changing sidebar behavior**: modify `class-walker-wiki.php` (PHP classes) + `assets/script.js` (toggle logic) + `assets/style.css` (visual states)
- **Changing template layout**: edit `templates/single-wiki.php`
- **Adding block attributes**: update `blocks/markdown/block.json`, `index.js`, and `render.php`
- **Version bumps**: update `WIPRESS_VERSION` in `wipress.php` to bust CSS/JS cache

## Documentation

Detailed docs in `docs/context/`:

- `overview.md` — architecture, concepts, layout
- `hierarchical-navigation.md` — sidebar tree, walker, TOC
- `markdown.md` — Parsedown, block, content filter
- `rest-api.md` — all endpoints with examples
- `mcp-server.md` — protocol, tools, resources, client config
- `getting-started.md` — installation and first wiki setup
