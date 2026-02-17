# Markdown Support

## Overview

WiPress supports Markdown content via two mechanisms:

1. **Gutenberg Markdown Block** — a custom block for writing Markdown in the editor with live preview
2. **Content Filter** — converts Markdown to HTML on `the_content` for posts with `_wipress_content_format = 'markdown'`

## Parsedown

WiPress bundles [Parsedown 1.7.4](https://parsedown.org/) (MIT license, single file) in `vendor/Parsedown.php`.

Configuration:

- `setMarkupEscaped(false)` — allows raw HTML in Markdown (images, videos, embeds)
- Output sanitized via `wp_kses_post()` — blocks `<script>`, `onclick`, etc. but allows `<img>`, `<a>`, `<video>`, `<iframe>` and other safe HTML

### Supported Markdown Syntax

All standard Markdown is supported:

```markdown
# Heading 1
## Heading 2
### Heading 3

**bold** and *italic* and `inline code`

- Unordered list
- Items

1. Ordered list
2. Items

[Link text](https://example.com)

![Image alt](https://example.com/image.png)

> Blockquote

---

| Table | Header |
|-------|--------|
| Cell  | Cell   |
```

### Raw HTML

You can embed raw HTML directly in Markdown content:

```html
<img src="./demo.gif" />
<video src="demo.mp4" controls></video>
<details>
  <summary>Click to expand</summary>
  Hidden content here.
</details>
```

## Gutenberg Markdown Block

### Adding the Block

1. In the block editor, click the **+** inserter
2. Search for **"Markdown"** (under Text category)
3. The block appears with a **Write** / **Preview** toggle

### Write Mode

- Monospace textarea for writing raw Markdown
- Tab size: 4 spaces
- Resizable vertically
- Placeholder: "Write markdown here..."

### Preview Mode

- Sends content to `POST /wipress/v1/render-markdown` endpoint
- Renders the HTML preview in the editor
- Updates on every content change when switching to Preview

### How It Works Internally

1. `block.json` registers the block with `apiVersion: 3`
2. `index.js` uses `wp.element.createElement` — no build step needed
3. `index.asset.php` declares dependencies: `wp-blocks`, `wp-element`, `wp-block-editor`, `wp-components`, `wp-api-fetch`
4. `render.php` calls `Wipress_Markdown::render()` for server-side output
5. The block saves `null` client-side (fully server-rendered)

### Block Attributes

| Attribute | Type | Description |
|-----------|------|-------------|
| `content` | string | Raw Markdown text |

## Content Filter

For posts that use the `_wipress_content_format` meta:

```php
// How the filter works (priority 5, before wpautop)
add_filter('the_content', [Wipress_Markdown, 'filter_content'], 5);
```

The filter only activates when:
1. Post type is `wiki`
2. Post meta `_wipress_content_format` equals `'markdown'`

When both conditions are met, the raw `post_content` is passed through Parsedown and `wp_kses_post()`.

### Setting Content Format via API

When creating or updating pages via REST API or MCP, pass `content_format: "markdown"`:

```bash
curl -X POST /wipress/v1/pages \
  -d '{"title":"My Page", "content":"# Hello", "content_format":"markdown"}'
```

This sets the `_wipress_content_format` meta, and the content filter will render it as Markdown on the frontend.

## CSS for Rendered Markdown

The `assets/style.css` includes styles for Markdown output inside `.wdh-render`:

- `h2`: top margin + bottom border separator
- `h3`, `h4`: appropriate top margins
- `code`: inline code with background highlight
- `pre`: code blocks with border, padding, and horizontal scroll
- `pre code`: removes inline code background inside code blocks
