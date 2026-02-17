<?php
if (!defined('ABSPATH')) exit;

class Wipress_Post_Type {

    public static function init() {
        add_action('init', [__CLASS__, 'register']);
        add_action('init', [__CLASS__, 'register_rewrite_rules']);
        add_filter('query_vars', [__CLASS__, 'register_query_vars']);
        add_filter('post_type_link', [__CLASS__, 'filter_post_type_link'], 10, 2);
        add_filter('request', [__CLASS__, 'filter_request']);
        add_action('enqueue_block_editor_assets', [__CLASS__, 'register_order_panel']);
    }

    public static function register() {
        register_post_type('wiki', [
            'public'       => true,
            'label'        => 'Wikis',
            'show_in_rest' => true,
            'hierarchical' => true,
            'supports'     => ['title', 'editor', 'revisions', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields'],
            'has_archive'  => false,
            'rewrite'      => false,
        ]);

        register_taxonomy('wiki_project', 'wiki', [
            'label'        => 'Proyectos',
            'hierarchical' => true,
            'show_in_rest' => true,
            'rewrite'      => ['slug' => 'wiki-project'],
        ]);

        register_taxonomy('wiki_section', 'wiki', [
            'label'        => 'Secciones',
            'hierarchical' => true,
            'show_in_rest' => true,
            'rewrite'      => ['slug' => 'wiki-section'],
        ]);

        register_post_meta('wiki', 'menu_order', [
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => 'integer',
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);
    }

    public static function register_rewrite_rules() {
        add_rewrite_rule('^wiki/?$', 'index.php?wipress_archive=1', 'top');
        add_rewrite_rule('^wiki/([^/]+)/?$', 'index.php?wipress_project_slug=$matches[1]', 'top');
        add_rewrite_rule('^wiki/([^/]+)/(.+?)/?$', 'index.php?wipress_page_path=$matches[2]&wipress_project_slug=$matches[1]', 'top');
    }

    public static function register_query_vars($vars) {
        $vars[] = 'wipress_archive';
        $vars[] = 'wipress_project_slug';
        $vars[] = 'wipress_page_path';
        $vars[] = 'wipress_not_found';
        return $vars;
    }

    public static function filter_post_type_link($post_link, $post) {
        if ($post->post_type !== 'wiki') return $post_link;

        $terms = wp_get_object_terms($post->ID, 'wiki_project');
        $project_slug = !empty($terms) && !is_wp_error($terms) ? $terms[0]->slug : 'uncategorized';

        return home_url('/wiki/' . $project_slug . '/' . get_page_uri($post) . '/');
    }

    public static function filter_request($query_vars) {
        if (empty($query_vars['wipress_page_path']) || empty($query_vars['wipress_project_slug'])) {
            return $query_vars;
        }

        $path = $query_vars['wipress_page_path'];
        $project_slug = $query_vars['wipress_project_slug'];

        $post = get_page_by_path($path, OBJECT, 'wiki');
        if (!$post) {
            unset($query_vars['wipress_project_slug']);
            $query_vars['wipress_not_found'] = 1;
            return $query_vars;
        }

        $terms = wp_get_object_terms($post->ID, 'wiki_project');
        $belongs = false;
        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                if ($term->slug === $project_slug) {
                    $belongs = true;
                    break;
                }
            }
        }

        if (!$belongs) {
            unset($query_vars['wipress_project_slug']);
            $query_vars['wipress_not_found'] = 1;
            return $query_vars;
        }

        unset($query_vars['wipress_page_path']);
        $query_vars['post_type'] = 'wiki';
        $query_vars['p'] = $post->ID;

        return $query_vars;
    }

    public static function register_order_panel() {
        global $post_type;
        if ($post_type !== 'wiki' && get_current_screen()->post_type !== 'wiki') return;

        wp_enqueue_script(
            'wipress-order-panel',
            WIPRESS_URL . 'assets/editor-order-panel.js',
            ['wp-plugins', 'wp-edit-post', 'wp-components', 'wp-data', 'wp-element'],
            WIPRESS_VERSION,
            true
        );
    }
}
