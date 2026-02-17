# Hierarchical Navigation

## Custom Post Type

The `wiki` CPT is registered with `hierarchical => true` and `page-attributes` support, which enables:

- **Parent selector** in the block editor sidebar (under the "Wikis" panel)
- **Menu Order** field via a custom editor panel ("Page Order")
- Parent/order exposed in the WordPress REST API (`/wp/v2/wiki`)

### Taxonomies

| Taxonomy | Purpose | Example Values |
|----------|---------|---------------|
| `wiki_project` | Groups pages into projects | "My App", "Internal Docs" |
| `wiki_section` | Tabs within a project | "Docs", "API", "Changelog" |

Both are hierarchical (like categories) and visible in the block editor sidebar.

### Private Projects

Projects have a "Public project" checkbox (term meta `_wipress_public`). When unchecked, the project and all its pages are hidden from:

- The `/wiki/` archive page
- REST API responses (`/projects`, `/pages`, `/search`)
- MCP server (scoped and global endpoints)
- Direct URL access (returns 404)

Only users with `edit_posts` capability (Administrator, Editor, Author, Contributor) can see private projects. Subscribers and anonymous visitors cannot. Existing projects default to public.

## Sidebar Search

Above the tree navigation, a search bar allows users to search wiki content within the current project. The component (`.wdh-search`) is rendered in two places: the desktop sidebar and the mobile drawer.

- Debounces input by 300ms, requires minimum 2 characters
- Fetches `GET /wipress/v1/search?q={query}&project={slug}` via `fetch()` with `AbortController` (cancels in-flight requests)
- Renders results as `<a>` links with title + excerpt using `textContent` (XSS-safe)
- Keyboard: Escape closes and clears; click outside closes dropdown
- Data attributes (`data-search-url`, `data-project`) pass REST API URL and project slug from PHP to JS

## Sidebar Tree (Walker)

The left sidebar renders a hierarchical tree using `Wipress_Walker`, which extends WordPress's `Walker` class.

### How It Works

1. `Wipress_Template::get_sidebar_posts()` queries all wiki pages in the current project + section, ordered by `menu_order ASC`
2. `Wipress_Walker::render_tree($posts, $current_post_id)` walks the posts and builds nested `<ul>/<li>` markup
3. The walker detects parent-child relationships via `post_parent`

### CSS Classes on `<li>` Elements

| Class | Meaning |
|-------|---------|
| `has-children` | This page has child pages |
| `is-folder` | Has children but NO content (acts as a container) |
| `active` | This is the currently viewed page |
| `ancestor` | This page is an ancestor of the current page |
| `expanded` | Children are visible (applied to ancestors only) |
| `collapsed` | Children are hidden (default for non-ancestor parents) |

### Expand/Collapse Behavior

- **Default state**: ALL branches are collapsed
- **Active page's ancestors**: automatically expanded (so you can see where you are in the tree)
- **Active page itself**: stays collapsed — its children don't auto-expand
- **Manual toggle**: click the chevron button or the folder name to expand/collapse

This means if you're viewing "For Linux Users", the tree looks like:

```
▾ Getting Started        (ancestor → expanded)
  ▾ Installation         (ancestor → expanded)
      For Mac Users
      For Linux Users    (active → highlighted)
      For Windows Users
    Configuration        (sibling → visible but not expanded)
    Common Issues
```

But "Configuration" and "Common Issues" are just visible as siblings — they don't expand their children (if they had any).

### Folder Pages

A folder page has children but no content (`post_content` is empty). In the sidebar:

- Renders as a `<span class="wdh-tree-folder">` instead of an `<a>` link
- Clicking it toggles expand/collapse — never navigates
- Accessible via keyboard (Enter/Space)

If a user navigates to a folder URL directly (e.g., `/wiki/getting-started/`), the template shows the page title and a list of child page links.

## Breadcrumbs

Above the page title, breadcrumb navigation shows the path from project root to the current page:

**Project Name** > **Ancestor Page** > **Current Page**

- Built from `get_post_ancestors()` (reversed to show root-first order)
- Project link points to `/wiki/{project-slug}/`
- Ancestor pages are clickable links
- Current page is plain text (no link)
- Uses chevron SVG separator (`>`)

## Prev/Next Navigation

At the bottom of each article, prev/next links help readers navigate sequentially through the sidebar tree:

- `Wipress_Template::get_prev_next($posts, $current_post_id)` flattens the sidebar tree into a linear list using `flatten_tree()` (depth-first traversal respecting `menu_order`)
- Finds the current page index, returns adjacent pages
- Previous link on the left, next link on the right
- Each link shows a label ("Previous"/"Next") with arrow icon and the page title
- On mobile, links stack vertically

## Last Updated Date

A "Last updated on {date}" line appears at the bottom of each article, before the prev/next navigation. Uses `get_the_modified_date('F j, Y')`.

## Keyboard Shortcut

**Cmd/Ctrl+K** focuses the sidebar search input. On mobile (viewport <= 768px), it first opens the drawer, then focuses the search input after the drawer transition completes (300ms delay).

## Table of Contents (Right Sidebar)

The TOC is generated client-side by `assets/script.js`:

1. Scans all `h2`, `h3`, `h4` elements inside `.wdh-render`
2. Generates slug-based IDs from heading text (e.g., "Get Started" → `get-started`)
3. Handles duplicate slugs by appending `-1`, `-2`, etc.
4. Creates a list of links with indentation by heading level

### Active Section Tracking

Uses `IntersectionObserver` (no scroll listener) with `rootMargin: '0px 0px -70% 0px'`:

- When a heading enters the top 30% of the viewport, its TOC link gets the `toc-active` class
- Active link shows a blue left border accent
- Clicking a TOC link smooth-scrolls to the heading and updates `location.hash` via `history.replaceState`

If the page has no headings, the TOC container is hidden entirely.

### Heading Anchor Links

Each h2, h3, h4 heading gets a `#` anchor link appended (class `.wdh-heading-anchor`):

- Hidden by default (`opacity: 0`), visible on heading hover
- Clicking scrolls smoothly to the heading and updates `location.hash` via `history.replaceState`
- Allows users to share direct links to specific sections

## Syntax Highlighting

Code blocks use **Prism.js** loaded from CDN:

- `prism.min.js` — core library
- `prism-autoloader.min.js` — automatically loads language grammars on demand (no need to bundle all languages)

Only loaded on singular wiki pages (`is_singular('wiki')`), enqueued in `Wipress_Template::enqueue_assets()`.

Token colors are defined in the plugin's `style.css` (not Prism's default theme) with separate color schemes for light and dark themes using `[data-theme="light"]` selectors. The dark theme uses One Dark-inspired colors; the light theme uses One Light-inspired colors.

## Menu Order in Block Editor

The `editor-order-panel.js` script adds a **"Page Order"** panel to the block editor sidebar:

- Only shows for `wiki` post type
- Input field for `menu_order` (integer)
- Lower numbers appear first in the sidebar
- Updates are saved with the post

## Creating a Navigation Structure

### Step-by-step

1. **Create a project**: In the block editor, assign a wiki page to a project taxonomy (e.g., "My App")
2. **Create a section**: Assign a section taxonomy (e.g., "Docs")
3. **Create parent pages**: Pages with no parent become root-level items
4. **Set parents**: Use the "Parent" dropdown in the block editor sidebar
5. **Set order**: Use the "Page Order" panel to control sort position
6. **Folder pages**: Leave content empty for pages that should just be containers

### Example Structure

| Page | Parent | Order | Content? |
|------|--------|-------|----------|
| Getting Started | (none) | 0 | No (folder) |
| Installation | Getting Started | 1 | No (folder) |
| For Mac Users | Installation | 0 | Yes |
| For Linux Users | Installation | 1 | Yes |
| Configuration | Getting Started | 2 | Yes |
