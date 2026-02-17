# Changelog

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
