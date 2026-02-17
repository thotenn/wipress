# Changelog

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
