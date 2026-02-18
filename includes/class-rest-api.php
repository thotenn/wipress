<?php
if (!defined('ABSPATH')) exit;

class Wipress_REST_API {

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        $ns = 'wipress/v1';

        // Markdown preview for block editor
        register_rest_route($ns, '/render-markdown', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_render_markdown'],
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        // Projects
        register_rest_route($ns, '/projects', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'handle_list_projects'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/projects/(?P<slug>[a-zA-Z0-9_-]+)/tree', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'handle_project_tree'],
            'permission_callback' => '__return_true',
        ]);

        // Pages
        register_rest_route($ns, '/pages', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'handle_list_pages'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'handle_create_page'],
                'permission_callback' => function() { return current_user_can('edit_posts'); },
            ],
        ]);

        register_rest_route($ns, '/pages/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'handle_get_page'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [__CLASS__, 'handle_update_page'],
                'permission_callback' => function() { return current_user_can('edit_posts'); },
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [__CLASS__, 'handle_delete_page'],
                'permission_callback' => function() { return current_user_can('edit_posts'); },
            ],
        ]);

        register_rest_route($ns, '/pages/(?P<id>\d+)/move', [
            'methods'             => 'PATCH',
            'callback'            => [__CLASS__, 'handle_move_page'],
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        // Search
        register_rest_route($ns, '/search', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'handle_search'],
            'permission_callback' => '__return_true',
        ]);

        // Export
        register_rest_route($ns, '/projects/(?P<slug>[a-zA-Z0-9_-]+)/export', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'handle_export_project'],
            'permission_callback' => '__return_true',
        ]);

        // Import
        register_rest_route($ns, '/import', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_import_project'],
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        // Legacy endpoint
        register_rest_route('wiki-devhub/v1', '/publish', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_legacy_publish'],
            'permission_callback' => function() { return current_user_can('edit_posts'); },
        ]);
    }

    // --- Markdown preview ---

    public static function handle_render_markdown($request) {
        $content = $request->get_param('content') ?? '';
        return ['html' => Wipress_Markdown::render($content)];
    }

    // --- Project visibility ---

    public static function is_project_visible($term) {
        if (current_user_can('edit_posts')) return true;
        if (is_numeric($term)) {
            $term = get_term((int)$term, 'wiki_project');
        }
        if (!$term || is_wp_error($term)) return false;
        $public = get_term_meta($term->term_id, '_wipress_public', true);
        return $public === '' || $public === '1';
    }

    private static function is_post_project_visible($post) {
        if (current_user_can('edit_posts')) return true;
        $terms = wp_get_object_terms($post->ID, 'wiki_project');
        return empty($terms) || self::is_project_visible($terms[0]);
    }

    // --- Internal methods (reused by MCP) ---

    public static function list_projects_internal() {
        $terms = get_terms(['taxonomy' => 'wiki_project', 'hide_empty' => false]);
        if (is_wp_error($terms)) return [];

        $projects = array_map(function($t) {
            return [
                'id'    => $t->term_id,
                'slug'  => $t->slug,
                'name'  => $t->name,
                'count' => $t->count,
            ];
        }, $terms);

        if (!current_user_can('edit_posts')) {
            $projects = array_values(array_filter($projects, function($p) {
                return self::is_project_visible($p['id']);
            }));
        }

        return $projects;
    }

    public static function list_sections_internal($project_slug = null) {
        $terms = get_terms(['taxonomy' => 'wiki_section', 'hide_empty' => false]);
        if (is_wp_error($terms)) return [];

        $sections = [];
        foreach ($terms as $t) {
            if ($project_slug) {
                $project = get_term_by('slug', $project_slug, 'wiki_project');
                if (!$project || !self::is_project_visible($project)) continue;
                $posts = get_posts([
                    'post_type'      => 'wiki',
                    'posts_per_page' => 1,
                    'tax_query'      => [
                        'relation' => 'AND',
                        ['taxonomy' => 'wiki_project', 'field' => 'term_id', 'terms' => $project->term_id],
                        ['taxonomy' => 'wiki_section', 'field' => 'term_id', 'terms' => $t->term_id],
                    ],
                ]);
                if (empty($posts)) continue;
            }
            $sections[] = [
                'id'   => $t->term_id,
                'slug' => $t->slug,
                'name' => $t->name,
            ];
        }
        return $sections;
    }

    public static function get_tree_internal($project_slug) {
        $project = get_term_by('slug', $project_slug, 'wiki_project');
        if (!$project) return new WP_Error('not_found', 'Project not found', ['status' => 404]);
        if (!self::is_project_visible($project)) return new WP_Error('not_found', 'Project not found', ['status' => 404]);

        $sections = self::list_sections_internal($project_slug);
        $tree = [];

        foreach ($sections as $section) {
            $posts = get_posts([
                'post_type'      => 'wiki',
                'posts_per_page' => -1,
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
                'post_status'    => 'publish',
                'tax_query'      => [
                    'relation' => 'AND',
                    ['taxonomy' => 'wiki_project', 'field' => 'term_id', 'terms' => $project->term_id],
                    ['taxonomy' => 'wiki_section', 'field' => 'term_id', 'terms' => $section['id']],
                ],
            ]);

            $tree[] = [
                'section'  => $section,
                'pages'    => self::build_page_tree($posts),
            ];
        }

        return $tree;
    }

    private static function build_page_tree($posts, $parent_id = 0) {
        $branch = [];
        foreach ($posts as $p) {
            if ((int)$p->post_parent !== $parent_id) continue;
            $branch[] = [
                'id'         => $p->ID,
                'title'      => $p->post_title,
                'slug'       => $p->post_name,
                'menu_order' => $p->menu_order,
                'url'        => get_permalink($p),
                'children'   => self::build_page_tree($posts, $p->ID),
            ];
        }
        return $branch;
    }

    public static function list_pages_internal($args = []) {
        $query_args = [
            'post_type'      => 'wiki',
            'posts_per_page' => $args['per_page'] ?? 100,
            'paged'          => $args['page'] ?? 1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ];

        $tax_query = [];
        if (!empty($args['project'])) {
            $tax_query[] = ['taxonomy' => 'wiki_project', 'field' => 'slug', 'terms' => $args['project']];
        }
        if (!empty($args['section'])) {
            $tax_query[] = ['taxonomy' => 'wiki_section', 'field' => 'slug', 'terms' => $args['section']];
        }

        // Exclude private projects for non-editors
        if (!current_user_can('edit_posts')) {
            $private_terms = get_terms([
                'taxonomy'   => 'wiki_project',
                'hide_empty' => false,
                'meta_query' => [['key' => '_wipress_public', 'value' => '0']],
                'fields'     => 'ids',
            ]);
            if (!empty($private_terms) && !is_wp_error($private_terms)) {
                $tax_query[] = [
                    'taxonomy' => 'wiki_project',
                    'field'    => 'term_id',
                    'terms'    => $private_terms,
                    'operator' => 'NOT IN',
                ];
            }
        }

        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }
        if (!empty($tax_query)) {
            $query_args['tax_query'] = $tax_query;
        }

        if (!empty($args['parent'])) {
            $query_args['post_parent'] = (int)$args['parent'];
        }

        if (!empty($args['search'])) {
            $query_args['s'] = $args['search'];
        }

        $posts = get_posts($query_args);
        return array_values(array_map([__CLASS__, 'format_page_summary'], $posts));
    }

    public static function get_page_internal($id) {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'wiki') {
            return new WP_Error('not_found', 'Page not found', ['status' => 404]);
        }
        if (!self::is_post_project_visible($post)) {
            return new WP_Error('not_found', 'Page not found', ['status' => 404]);
        }
        return self::format_page_full($post);
    }

    public static function create_page_internal($data) {
        $args = [
            'post_type'   => 'wiki',
            'post_title'  => sanitize_text_field($data['title'] ?? ''),
            'post_content'=> wp_kses_post($data['content'] ?? ''),
            'post_status' => 'publish',
        ];

        if (!empty($data['parent'])) $args['post_parent'] = (int)$data['parent'];
        if (isset($data['menu_order'])) $args['menu_order'] = (int)$data['menu_order'];

        $post_id = wp_insert_post($args, true);
        if (is_wp_error($post_id)) return $post_id;

        if (!empty($data['content_format'])) {
            if (!in_array($data['content_format'], ['html', 'markdown'], true)) {
                wp_delete_post($post_id, true);
                return new WP_Error('invalid_format', 'content_format must be "html" or "markdown"', ['status' => 400]);
            }
            update_post_meta($post_id, '_wipress_content_format', $data['content_format']);
        }

        self::set_taxonomies($post_id, $data);

        return self::get_page_internal($post_id);
    }

    public static function update_page_internal($id, $data) {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'wiki') {
            return new WP_Error('not_found', 'Page not found', ['status' => 404]);
        }

        $args = ['ID' => $id];
        if (isset($data['title'])) $args['post_title'] = sanitize_text_field($data['title']);
        if (isset($data['content'])) $args['post_content'] = wp_kses_post($data['content']);
        if (isset($data['menu_order'])) $args['menu_order'] = (int)$data['menu_order'];
        if (isset($data['parent'])) $args['post_parent'] = (int)$data['parent'];
        if (isset($data['status'])) $args['post_status'] = sanitize_text_field($data['status']);

        $result = wp_update_post($args, true);
        if (is_wp_error($result)) return $result;

        if (!empty($data['content_format'])) {
            if (!in_array($data['content_format'], ['html', 'markdown'], true)) {
                return new WP_Error('invalid_format', 'content_format must be "html" or "markdown"', ['status' => 400]);
            }
            update_post_meta($id, '_wipress_content_format', $data['content_format']);
        }

        self::set_taxonomies($id, $data);

        return self::get_page_internal($id);
    }

    public static function delete_page_internal($id) {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'wiki') {
            return new WP_Error('not_found', 'Page not found', ['status' => 404]);
        }
        $result = wp_delete_post($id, true);
        if (!$result) {
            return new WP_Error('delete_failed', 'Failed to delete page', ['status' => 500]);
        }
        return ['deleted' => true, 'id' => $id];
    }

    public static function move_page_internal($id, $data) {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'wiki') {
            return new WP_Error('not_found', 'Page not found', ['status' => 404]);
        }

        $args = ['ID' => $id];
        if (isset($data['parent'])) $args['post_parent'] = (int)$data['parent'];
        if (isset($data['menu_order'])) $args['menu_order'] = (int)$data['menu_order'];

        $result = wp_update_post($args, true);
        if (is_wp_error($result)) return $result;

        return self::get_page_internal($id);
    }

    public static function search_internal($query, $project = null) {
        $args = [
            'post_type'      => 'wiki',
            'posts_per_page' => 20,
            's'              => $query,
            'post_status'    => 'publish',
        ];

        if ($project) {
            $args['tax_query'] = [
                ['taxonomy' => 'wiki_project', 'field' => 'slug', 'terms' => $project],
            ];
        }

        $posts = get_posts($args);
        $posts = array_filter($posts, [__CLASS__, 'is_post_project_visible']);
        $posts = array_values($posts);
        return array_map(function($p) use ($query) {
            $summary = self::format_page_summary($p);
            // Add excerpt with search context
            $content = wp_strip_all_tags($p->post_content);
            $pos = stripos($content, $query);
            if ($pos !== false) {
                $start = max(0, $pos - 80);
                $summary['excerpt'] = '...' . substr($content, $start, 200) . '...';
            } else {
                $summary['excerpt'] = wp_trim_words($content, 30);
            }
            return $summary;
        }, $posts);
    }

    // --- Helpers ---

    public static function set_taxonomies($post_id, $data) {
        if (!empty($data['project'])) {
            $slug = sanitize_title($data['project']);
            $term = term_exists($slug, 'wiki_project') ?: wp_insert_term($slug, 'wiki_project');
            $term_id = is_array($term) ? $term['term_id'] : $term;
            wp_set_object_terms($post_id, [(int)$term_id], 'wiki_project');
        }
        if (!empty($data['section'])) {
            $name = sanitize_text_field($data['section']);
            $term = term_exists($name, 'wiki_section') ?: wp_insert_term($name, 'wiki_section');
            $term_id = is_array($term) ? $term['term_id'] : $term;
            wp_set_object_terms($post_id, [(int)$term_id], 'wiki_section');
        }
    }

    private static function format_page_summary($post) {
        $projects = wp_get_object_terms($post->ID, 'wiki_project');
        $sections = wp_get_object_terms($post->ID, 'wiki_section');

        return [
            'id'         => $post->ID,
            'title'      => $post->post_title,
            'slug'       => $post->post_name,
            'parent'     => $post->post_parent,
            'menu_order' => $post->menu_order,
            'url'        => get_permalink($post),
            'project'    => !empty($projects) ? $projects[0]->slug : null,
            'section'    => !empty($sections) ? $sections[0]->slug : null,
        ];
    }

    private static function format_page_full($post) {
        $summary = self::format_page_summary($post);
        $summary['content'] = $post->post_content;
        $summary['content_format'] = get_post_meta($post->ID, '_wipress_content_format', true) ?: 'html';
        $summary['modified'] = $post->post_modified;
        $summary['status'] = $post->post_status;
        return $summary;
    }

    // --- Route handlers ---

    public static function handle_list_projects() {
        return rest_ensure_response(self::list_projects_internal());
    }

    public static function handle_project_tree($request) {
        $result = self::get_tree_internal($request['slug']);
        if (is_wp_error($result)) return $result;
        return rest_ensure_response($result);
    }

    public static function handle_list_pages($request) {
        return rest_ensure_response(self::list_pages_internal([
            'project'  => $request->get_param('project'),
            'section'  => $request->get_param('section'),
            'parent'   => $request->get_param('parent'),
            'search'   => $request->get_param('search'),
            'page'     => $request->get_param('page'),
            'per_page' => $request->get_param('per_page'),
        ]));
    }

    public static function handle_get_page($request) {
        $result = self::get_page_internal($request['id']);
        if (is_wp_error($result)) return $result;
        return rest_ensure_response($result);
    }

    public static function handle_create_page($request) {
        $result = self::create_page_internal($request->get_json_params());
        if (is_wp_error($result)) return $result;
        return rest_ensure_response($result);
    }

    public static function handle_update_page($request) {
        $result = self::update_page_internal($request['id'], $request->get_json_params());
        if (is_wp_error($result)) return $result;
        return rest_ensure_response($result);
    }

    public static function handle_delete_page($request) {
        $result = self::delete_page_internal($request['id']);
        if (is_wp_error($result)) return $result;
        return rest_ensure_response($result);
    }

    public static function handle_move_page($request) {
        $result = self::move_page_internal($request['id'], $request->get_json_params());
        if (is_wp_error($result)) return $result;
        return rest_ensure_response($result);
    }

    public static function handle_search($request) {
        $query = $request->get_param('q');
        if (empty($query)) {
            return new WP_Error('missing_query', 'Search query is required', ['status' => 400]);
        }
        return rest_ensure_response(self::search_internal($query, $request->get_param('project')));
    }

    // --- Export / Import ---

    public static function handle_export_project($request) {
        $result = Wipress_Import_Export::export_project_internal($request['slug']);
        if (is_wp_error($result)) return $result;
        return rest_ensure_response($result);
    }

    public static function handle_import_project($request) {
        $data = $request->get_json_params();
        $mode = $request->get_param('mode') ?: 'replace';
        $result = Wipress_Import_Export::import_project_internal($data, $mode);
        if (is_wp_error($result)) return $result;
        return rest_ensure_response($result);
    }

    // --- Legacy ---

    public static function handle_legacy_publish($request) {
        $params = $request->get_params();
        $result = self::create_page_internal([
            'title'   => $params['title'] ?? '',
            'content' => $params['content'] ?? '',
            'project' => $params['project'] ?? '',
            'section' => $params['section'] ?? 'Docs',
        ]);
        if (is_wp_error($result)) return $result;
        return ['success' => true, 'url' => $result['url']];
    }
}
