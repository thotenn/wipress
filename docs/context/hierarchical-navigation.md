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
