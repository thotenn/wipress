<?php
if (!defined('ABSPATH')) exit;

class Wipress_MCP_Server {

    const PROTOCOL_VERSION = '2024-11-05';

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_route']);
    }

    public static function register_route() {
        register_rest_route('wipress/v1', '/mcp', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_request'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handle_request($request) {
        $body = $request->get_json_params();

        if (empty($body) || !isset($body['jsonrpc']) || $body['jsonrpc'] !== '2.0') {
            return self::json_rpc_error(null, -32600, 'Invalid JSON-RPC 2.0 request');
        }

        $method = $body['method'] ?? '';
        $params = $body['params'] ?? [];
        $id     = $body['id'] ?? null;

        switch ($method) {
            case 'initialize':
                return self::json_rpc_response($id, [
                    'protocolVersion' => self::PROTOCOL_VERSION,
                    'capabilities'    => [
                        'tools' => new stdClass,
                    ],
                    'serverInfo' => [
                        'name'    => 'wipress',
                        'version' => WIPRESS_VERSION,
                    ],
                ]);

            case 'notifications/initialized':
                return new WP_REST_Response(null, 204);

            case 'tools/list':
                $tools = [];
                foreach (self::get_tools() as $name => $def) {
                    $tools[] = [
                        'name'        => $name,
                        'description' => $def['description'],
                        'inputSchema' => $def['inputSchema'],
                    ];
                }
                return self::json_rpc_response($id, ['tools' => $tools]);

            case 'tools/call':
                return self::handle_tool_call($id, $params);

            case 'resources/list':
                return self::json_rpc_response($id, ['resources' => self::get_resources()]);

            case 'resources/read':
                return self::handle_resource_read($id, $params);

            default:
                return self::json_rpc_error($id, -32601, "Method not found: $method");
        }
    }

    private static function get_tools() {
        // Build tools array at runtime to avoid stdClass serialization issues
        $tools = [];

        $tools['wiki_list_projects'] = [
            'description' => 'List all wiki projects',
            'inputSchema' => ['type' => 'object', 'properties' => []],
        ];
        $tools['wiki_list_sections'] = [
            'description' => 'List sections, optionally filtered by project',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'project' => ['type' => 'string', 'description' => 'Project slug to filter by'],
                ],
            ],
        ];
        $tools['wiki_get_tree'] = [
            'description' => 'Get the full navigation tree for a project',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'project' => ['type' => 'string', 'description' => 'Project slug'],
                ],
                'required' => ['project'],
            ],
        ];
        $tools['wiki_list_pages'] = [
            'description' => 'List wiki pages with optional filters',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'project' => ['type' => 'string', 'description' => 'Filter by project slug'],
                    'section' => ['type' => 'string', 'description' => 'Filter by section slug'],
                    'parent'  => ['type' => 'integer', 'description' => 'Filter by parent page ID'],
                    'search'  => ['type' => 'string', 'description' => 'Search query'],
                ],
            ],
        ];
        $tools['wiki_get_page'] = [
            'description' => 'Get a wiki page by ID with full content',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Page ID'],
                ],
                'required' => ['id'],
            ],
        ];
        $tools['wiki_create_page'] = [
            'description' => 'Create a new wiki page',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'title'          => ['type' => 'string', 'description' => 'Page title'],
                    'content'        => ['type' => 'string', 'description' => 'Page content (markdown or html)'],
                    'content_format' => ['type' => 'string', 'enum' => ['markdown', 'html'], 'description' => 'Content format'],
                    'project'        => ['type' => 'string', 'description' => 'Project slug'],
                    'section'        => ['type' => 'string', 'description' => 'Section name'],
                    'parent'         => ['type' => 'integer', 'description' => 'Parent page ID'],
                    'menu_order'     => ['type' => 'integer', 'description' => 'Sort order'],
                ],
                'required' => ['title', 'content'],
            ],
        ];
        $tools['wiki_update_page'] = [
            'description' => 'Update an existing wiki page',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id'             => ['type' => 'integer', 'description' => 'Page ID'],
                    'title'          => ['type' => 'string', 'description' => 'New title'],
                    'content'        => ['type' => 'string', 'description' => 'New content'],
                    'content_format' => ['type' => 'string', 'enum' => ['markdown', 'html'], 'description' => 'Content format'],
                ],
                'required' => ['id'],
            ],
        ];
        $tools['wiki_delete_page'] = [
            'description' => 'Delete a wiki page permanently',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Page ID to delete'],
                ],
                'required' => ['id'],
            ],
        ];
        $tools['wiki_move_page'] = [
            'description' => 'Move a wiki page to a new parent or change its order',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id'         => ['type' => 'integer', 'description' => 'Page ID'],
                    'parent'     => ['type' => 'integer', 'description' => 'New parent page ID (0 for root)'],
                    'menu_order' => ['type' => 'integer', 'description' => 'New sort order'],
                ],
                'required' => ['id'],
            ],
        ];
        $tools['wiki_search'] = [
            'description' => 'Search wiki content',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'query'   => ['type' => 'string', 'description' => 'Search query'],
                    'project' => ['type' => 'string', 'description' => 'Limit search to project slug'],
                ],
                'required' => ['query'],
            ],
        ];

        return $tools;
    }

    private static function handle_tool_call($id, $params) {
        $tool_name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        // Write operations require authentication
        $write_tools = ['wiki_create_page', 'wiki_update_page', 'wiki_delete_page', 'wiki_move_page'];
        if (in_array($tool_name, $write_tools) && !current_user_can('edit_posts')) {
            return self::json_rpc_response($id, [
                'content' => [['type' => 'text', 'text' => 'Error: Authentication required for write operations']],
                'isError' => true,
            ]);
        }

        $result = self::dispatch_tool($tool_name, $arguments);

        if (is_wp_error($result)) {
            return self::json_rpc_response($id, [
                'content' => [['type' => 'text', 'text' => 'Error: ' . $result->get_error_message()]],
                'isError' => true,
            ]);
        }

        $text = is_string($result) ? $result : wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return self::json_rpc_response($id, [
            'content' => [['type' => 'text', 'text' => $text]],
        ]);
    }

    private static function dispatch_tool($name, $args) {
        switch ($name) {
            case 'wiki_list_projects':
                return Wipress_REST_API::list_projects_internal();

            case 'wiki_list_sections':
                return Wipress_REST_API::list_sections_internal($args['project'] ?? null);

            case 'wiki_get_tree':
                return Wipress_REST_API::get_tree_internal($args['project'] ?? '');

            case 'wiki_list_pages':
                return Wipress_REST_API::list_pages_internal($args);

            case 'wiki_get_page':
                return Wipress_REST_API::get_page_internal($args['id'] ?? 0);

            case 'wiki_create_page':
                return Wipress_REST_API::create_page_internal($args);

            case 'wiki_update_page':
                $id = $args['id'] ?? 0;
                unset($args['id']);
                return Wipress_REST_API::update_page_internal($id, $args);

            case 'wiki_delete_page':
                return Wipress_REST_API::delete_page_internal($args['id'] ?? 0);

            case 'wiki_move_page':
                $id = $args['id'] ?? 0;
                unset($args['id']);
                return Wipress_REST_API::move_page_internal($id, $args);

            case 'wiki_search':
                return Wipress_REST_API::search_internal($args['query'] ?? '', $args['project'] ?? null);

            default:
                return new WP_Error('unknown_tool', "Unknown tool: $name");
        }
    }

    private static function get_resources() {
        $resources = [];
        $projects = Wipress_REST_API::list_projects_internal();
        foreach ($projects as $p) {
            $resources[] = [
                'uri'         => 'wiki://project/' . $p['slug'],
                'name'        => $p['name'],
                'description' => "Wiki project: {$p['name']} ({$p['count']} pages)",
                'mimeType'    => 'application/json',
            ];
        }
        return $resources;
    }

    private static function handle_resource_read($id, $params) {
        $uri = $params['uri'] ?? '';

        if (preg_match('#^wiki://project/([^/]+)$#', $uri, $m)) {
            $tree = Wipress_REST_API::get_tree_internal($m[1]);
            if (is_wp_error($tree)) {
                return self::json_rpc_error($id, -32602, $tree->get_error_message());
            }
            return self::json_rpc_response($id, [
                'contents' => [[
                    'uri'      => $uri,
                    'mimeType' => 'application/json',
                    'text'     => wp_json_encode($tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                ]],
            ]);
        }

        return self::json_rpc_error($id, -32602, "Unknown resource URI: $uri");
    }

    private static function json_rpc_response($id, $result) {
        return rest_ensure_response([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ]);
    }

    private static function json_rpc_error($id, $code, $message) {
        return rest_ensure_response([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => ['code' => $code, 'message' => $message],
        ]);
    }
}
