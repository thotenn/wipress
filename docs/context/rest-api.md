# REST API

## Base URL

```
http://your-site.com/wp-json/wipress/v1
```

If pretty permalinks are not enabled, use the query parameter format:

```
http://your-site.com/?rest_route=/wipress/v1/endpoint
```

## Authentication

- **Read endpoints** — public, no authentication required
- **Write endpoints** — require `edit_posts` capability via WordPress Application Passwords (Basic Auth)

### Setting Up Application Passwords

1. Go to `wp-admin > Users > Your Profile`
2. Scroll to **Application Passwords**
3. Enter a name (e.g., "API Access") and click **Add New**
4. Copy the generated password (spaces included)

### Using Authentication

```bash
curl -u "username:xxxx xxxx xxxx xxxx" \
  -X POST http://localhost:5580/wp-json/wipress/v1/pages \
  -H "Content-Type: application/json" \
  -d '{"title":"New Page","content":"Hello"}'
```

> **Note**: Application Passwords require HTTPS by default. WiPress enables them over HTTP for local development via the `wp_is_application_passwords_available` filter.

## Endpoints

### Projects

#### `GET /projects`

List all wiki projects.

**Response:**
```json
[
  {
    "id": 3,
    "slug": "my-project",
    "name": "My Project",
    "count": 12
  }
]
```

#### `GET /projects/{slug}/tree`

Get the full navigation tree for a project, organized by sections.

**Response:**
```json
[
  {
    "section": { "id": 5, "slug": "docs", "name": "Docs" },
    "pages": [
      {
        "id": 16,
        "title": "Getting Started",
        "slug": "getting-started",
        "menu_order": 0,
        "url": "http://example.com/wiki/getting-started",
        "children": [
          {
            "id": 18,
            "title": "Installation",
            "slug": "installation",
            "menu_order": 1,
            "url": "http://example.com/wiki/getting-started/installation",
            "children": [ ... ]
          }
        ]
      }
    ]
  }
]
```

### Pages

#### `GET /pages`

List pages with optional filters.

**Query Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `project` | string | Filter by project slug |
| `section` | string | Filter by section slug |
| `parent` | integer | Filter by parent page ID |
| `search` | string | Search in title/content |
| `page` | integer | Page number (default: 1) |
| `per_page` | integer | Items per page (default: 100) |

**Example:**
```bash
curl "http://localhost:5580/wp-json/wipress/v1/pages?project=my-project&section=docs"
```

**Response:**
```json
[
  {
    "id": 16,
    "title": "Getting Started",
    "slug": "getting-started",
    "parent": 0,
    "menu_order": 0,
    "url": "http://example.com/wiki/getting-started",
    "project": "my-project",
    "section": "docs"
  }
]
```

#### `GET /pages/{id}`

Get a single page with full content.

**Response:**
```json
{
  "id": 26,
  "title": "For Linux Users",
  "slug": "for-linux-users",
  "parent": 18,
  "menu_order": 1,
  "url": "http://example.com/wiki/getting-started/installation/for-linux-users",
  "project": "my-project",
  "section": "docs",
  "content": "## Linux Installation\n\nFollow these steps...",
  "content_format": "markdown",
  "modified": "2026-02-17 01:00:00",
  "status": "publish"
}
```

#### `POST /pages` (auth required)

Create a new wiki page.

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | yes | Page title |
| `content` | string | yes | Page content (HTML or Markdown) |
| `content_format` | string | no | `"html"` (default) or `"markdown"` |
| `project` | string | no | Project slug (creates if new) |
| `section` | string | no | Section name (creates if new) |
| `parent` | integer | no | Parent page ID |
| `menu_order` | integer | no | Sort order |

**Example:**
```bash
curl -X POST http://localhost:5580/wp-json/wipress/v1/pages \
  -u "admin:xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Deployment",
    "content": "## Deploy to Production\n\n1. Build the project\n2. Upload files",
    "content_format": "markdown",
    "project": "my-project",
    "section": "Docs",
    "parent": 16,
    "menu_order": 5
  }'
```

#### `PUT /pages/{id}` (auth required)

Update an existing page. Only include fields you want to change.

**Request Body:**

| Field | Type | Description |
|-------|------|-------------|
| `title` | string | New title |
| `content` | string | New content |
| `content_format` | string | `"html"` or `"markdown"` |
| `menu_order` | integer | New sort order |
| `parent` | integer | New parent page ID |
| `status` | string | Post status (publish, draft, etc.) |

**Example:**
```bash
curl -X PUT http://localhost:5580/wp-json/wipress/v1/pages/35 \
  -u "admin:xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"title": "Updated Title", "content": "New content here"}'
```

#### `DELETE /pages/{id}` (auth required)

Permanently delete a page.

**Response:**
```json
{ "deleted": true, "id": 35 }
```

#### `PATCH /pages/{id}/move` (auth required)

Move a page to a different parent or change its order.

**Request Body:**

| Field | Type | Description |
|-------|------|-------------|
| `parent` | integer | New parent page ID (0 for root) |
| `menu_order` | integer | New sort order |

**Example:**
```bash
curl -X PATCH http://localhost:5580/wp-json/wipress/v1/pages/35/move \
  -u "admin:xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"parent": 18, "menu_order": 3}'
```

### Search

#### `GET /search`

Full-text search across wiki content.

**Query Parameters:**

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `q` | string | yes | Search query |
| `project` | string | no | Limit to project slug |

**Example:**
```bash
curl "http://localhost:5580/wp-json/wipress/v1/search?q=installation&project=my-project"
```

**Response:**
```json
[
  {
    "id": 37,
    "title": "For Windows Users",
    "slug": "for-windows-users",
    "parent": 18,
    "menu_order": 2,
    "url": "http://example.com/wiki/getting-started/installation/for-windows-users",
    "project": "my-project",
    "section": "docs",
    "excerpt": "...## Windows Installation\n\nDownload the installer from the releases page..."
  }
]
```

### Markdown Preview

#### `POST /render-markdown` (auth required)

Render Markdown to HTML. Used by the Gutenberg block for live preview.

**Request Body:**
```json
{ "content": "## Hello\n\nThis is **bold**." }
```

**Response:**
```json
{ "html": "<h2>Hello</h2>\n<p>This is <strong>bold</strong>.</p>" }
```

### Legacy Endpoint

#### `POST /wiki-devhub/v1/publish` (auth required)

Backward-compatible endpoint from the original plugin. Creates or updates a page.

**Request Body:**
```json
{
  "title": "Page Title",
  "content": "Page content",
  "project": "project-slug",
  "section": "Docs"
}
```

**Response:**
```json
{ "success": true, "url": "http://example.com/wiki/page-title" }
```

## Workflows

### Bulk Import Documentation

```bash
AUTH="admin:xxxx xxxx xxxx xxxx"
API="http://localhost:5580/wp-json/wipress/v1"

# Create project structure
curl -X POST "$API/pages" -u "$AUTH" -H "Content-Type: application/json" \
  -d '{"title":"API Reference","project":"my-app","section":"API","menu_order":0}'

# Create child pages
curl -X POST "$API/pages" -u "$AUTH" -H "Content-Type: application/json" \
  -d '{"title":"Authentication","content":"# Auth\n\nUse Bearer tokens...","content_format":"markdown","project":"my-app","section":"API","parent":PARENT_ID,"menu_order":0}'
```

### Reorganize Pages

```bash
# Move page to different parent
curl -X PATCH "$API/pages/42/move" -u "$AUTH" -H "Content-Type: application/json" \
  -d '{"parent":18,"menu_order":0}'

# Reorder siblings
curl -X PATCH "$API/pages/24/move" -u "$AUTH" -d '{"menu_order":0}'
curl -X PATCH "$API/pages/26/move" -u "$AUTH" -d '{"menu_order":1}'
curl -X PATCH "$API/pages/37/move" -u "$AUTH" -d '{"menu_order":2}'
```
