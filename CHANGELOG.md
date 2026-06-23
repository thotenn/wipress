# Changelog

## 1.0.27

### Fixes
- Fix Markdown double-escaping: `wp_kses_post()` is no longer applied when saving markdown pages (it escaped `<`/`>` to entities that Parsedown then re-escaped, showing a literal `&gt;`). Markdown is now stored verbatim and sanitized at render time
- Saving markdown now disables the `content_save_pre` kses filter, so content is not corrupted for users without the `unfiltered_html` capability (REST/MCP with lower roles)

> Note: content already stored with this bug must be repaired separately (decode `&lt;`/`&gt;`/`&amp;` in existing markdown pages).

## 1.0.26

Comprehensive audit of performance, accessibility, mobile and i18n (see `docs/tickets/0-update-ticket/`).

### Fixes
- Fix archive page (`/wiki/`) layout: the project list is no longer squeezed into 260px (`.wdh-inf-content--full` class defined)
- The archive page now respects the light/dark theme (theme bootstrap added)
- Fix TOC text: it no longer includes a trailing `#` picked up from the heading anchor

### Performance
- Removed N+1 queries: `wp_get_object_terms()` → `get_the_terms()` (uses the term cache) in REST, templates and permalinks
- `list_sections_internal()`: project lookup hoisted out of the loop
- `get_tree_internal()`: a single page query + grouping by section (previously O(sections) queries)
- Transient cache for the navigation tree, invalidated when saving pages or editing projects/sections

### Accessibility and mobile
- Wide tables now scroll horizontally on mobile (wrapper)
- Collapsible "On this page" TOC on tablet/mobile (where the right sidebar was hidden)
- Download Markdown and Copy MCP URL actions also available on mobile
- Keyboard navigation in the search box (↑/↓/Enter) + ARIA roles
- `aria-label` on tree toggles, "Skip to content" skip link, `:focus-visible` styles
- `prefers-reduced-motion` support (CSS + programmatic scrolling)
- Clipboard fallback for non-secure (HTTP) contexts
- Content images with `loading="lazy"` / `decoding="async"`

### Internationalization
- `wipress` text domain loaded; all user-facing strings (PHP and JS) wrapped via `__()`/`wp_localize_script`
- Multibyte-safe search excerpts (`mb_stripos`/`mb_substr`)

### Dependencies and cleanup
- Prism.js served locally from `vendor/prism/` (no external CDN)
- Removed the redundant `menu_order` post meta (it shadowed the native field)

## 1.0.7

- Add breadcrumb navigation above page title (Project > Ancestor > Current Page)
- Add prev/next page navigation at the bottom of each wiki page
- Add heading anchor links (# symbol appears on hover, click to copy link)
- Add syntax highlighting via Prism.js CDN with autoloader for on-demand language support
- Add "Last updated" date at the bottom of each article
- Add Cmd/Ctrl+K keyboard shortcut to focus sidebar search (opens drawer on mobile)
- Add `build.sh` script for creating distributable .zip (excludes .git/, docs/, CLAUDE.md, README.md)

## 1.0.6

- Fix taxonomy labels: "Add Category" / "Edit Category" now correctly show "Add New Project" / "Edit Project" and "Add New Section" / "Edit Section"

## 1.0.5

- Add private project support: "Public project" checkbox on project add/edit screens
- Private projects hidden from `/wiki/` archive, REST API, MCP server, search results, and direct page access
- Only users with `edit_posts` capability (Administrator, Editor, Author, Contributor) can view private projects
- Existing projects default to public for backward compatibility
- Fix bug in `archive-wiki.php` where PHP code was rendered as raw text (missing `<?php` tag)

## 1.0.4

- Add copy-to-clipboard button on code blocks (`<pre>`) in rendered content — appears on hover, shows checkmark feedback after copying

## 1.0.3

- Add sidebar search bar above the tree navigation (desktop + mobile drawer)
- Search uses existing REST endpoint `GET /wipress/v1/search` with 300ms debounce, AbortController, and XSS-safe rendering via `textContent`
- Dropdown results show title + excerpt as navigable links
- Keyboard support: Escape closes dropdown; click outside dismisses

## 1.0.2

- Initial stable release with hierarchical wiki pages, Markdown support, REST API, and MCP server
