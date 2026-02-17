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
assets/                  → frontend CSS/JS (search, code copy, TOC, tree toggle, Prism.js theming) + editor panel JS
build.sh                 → zip build script for distribution (excludes .git/, docs/, CLAUDE.md, README.md)
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
- **Sidebar search** — each `.wdh-search` instance initialized independently (desktop + mobile drawer), uses REST endpoint `GET /wipress/v1/search`, 300ms debounce, AbortController for in-flight requests, `textContent` only (XSS-safe)
- **Code copy button** — injected via JS into every `<pre>` inside `.wdh-render`, uses `navigator.clipboard.writeText()`, appears on hover
- **Private projects** — term meta `_wipress_public` on `wiki_project` taxonomy. Default `'1'` (public). When `'0'`, project is hidden from non-editors. Checked via `Wipress_REST_API::is_project_visible($term)` in REST API, MCP, template loader, and archive page
- **Breadcrumbs** — built from `get_post_ancestors()`, shows Project > Ancestor > Current Page
- **Prev/next navigation** — `get_prev_next()` flattens the sidebar tree and finds adjacent pages. Displayed at bottom of article
- **Heading anchor links** — `#` symbol appended to h2/h3/h4 via JS, visible on hover, click scrolls and updates URL hash
- **Syntax highlighting** — Prism.js loaded from CDN with autoloader plugin. Token colors defined in plugin CSS with light/dark theme variants
- **Cmd/Ctrl+K shortcut** — focuses sidebar search input; on mobile opens drawer first then focuses search after transition

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

## Term Meta

| Key | Taxonomy | Values | Description |
|-----|----------|--------|-------------|
| `_wipress_public` | `wiki_project` | `'1'` (default), `'0'` | Project visibility. `'0'` = private (only visible to users with `edit_posts` capability) |

## Taxonomies

| Taxonomy | Post Type | Purpose |
|----------|-----------|---------|
| `wiki_project` | wiki | Project grouping |
| `wiki_section` | wiki | Section tabs within project |

## When Modifying

- **Adding a REST endpoint**: add route in `register_routes()`, create handler + `*_internal()` static method
- **Adding an MCP tool**: add to `get_tools()` array, add case in `dispatch_tool()`, delegate to REST API internal
- **Changing sidebar behavior**: modify `class-walker-wiki.php` (PHP classes) + `assets/script.js` (toggle logic + search) + `assets/style.css` (visual states)
- **Changing sidebar search**: modify search HTML in `templates/single-wiki.php` (two instances: desktop + mobile), JS in `assets/script.js` (search section), CSS in `assets/style.css` (`.wdh-search*` classes)
- **Changing code block copy button**: modify JS in `assets/script.js` (code copy section), CSS in `assets/style.css` (`.wdh-code-copy` class)
- **Changing project visibility**: checkbox saved as term meta `_wipress_public` in `class-post-type.php`, filtering via `is_project_visible()` in `class-rest-api.php`, enforced in `class-template.php` and `class-mcp-server.php`
- **Changing template layout**: edit `templates/single-wiki.php`
- **Adding block attributes**: update `blocks/markdown/block.json`, `index.js`, and `render.php`
- **Version bumps**: update `WIPRESS_VERSION` in `wipress.php` to bust CSS/JS cache
- **Changing breadcrumbs/prev-next**: modify HTML in `templates/single-wiki.php`, helpers in `class-template.php` (`get_prev_next`, `flatten_tree`), CSS in `assets/style.css` (`.wdh-breadcrumbs`, `.wdh-prev-next*`)
- **Changing heading anchors**: modify JS in `assets/script.js` (TOC generation section, `.wdh-heading-anchor`), CSS in `assets/style.css`
- **Changing syntax highlighting**: token colors in `assets/style.css` (`.token.*` rules with `[data-theme="light"]` variants), Prism.js CDN URLs in `class-template.php` (`enqueue_assets`)
- **Building for distribution**: run `./build.sh` — creates `wipress-{version}.zip` excluding dev files
- **IMPORTANT — Always bump version**: after finishing ANY code modification (PHP, CSS, JS, templates), increment `WIPRESS_VERSION` in `wipress.php` before committing. Server-side caches (W3TC, CDN) differentiate cached assets by the `?ver=` query string, and stale versions will be served to some browsers if the version is not bumped

## Documentation

Detailed docs in `docs/context/`:

- `overview.md` — architecture, concepts, layout
- `hierarchical-navigation.md` — sidebar tree, walker, TOC
- `markdown.md` — Parsedown, block, content filter
- `rest-api.md` — all endpoints with examples
- `mcp-server.md` — protocol, tools, resources, client config
- `getting-started.md` — installation and first wiki setup
