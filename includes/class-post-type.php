<?php
if (!defined('ABSPATH')) exit;

class Wipress_Post_Type {

    public static function init() {
        add_action('init', [__CLASS__, 'register']);
        add_action('enqueue_block_editor_assets', [__CLASS__, 'register_order_panel']);
    }

    public static function register() {
        register_post_type('wiki', [
            'public'       => true,
            'label'        => 'Wikis',
            'show_in_rest' => true,
            'hierarchical' => true,
            'supports'     => ['title', 'editor', 'revisions', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields'],
            'has_archive'  => true,
            'rewrite'      => ['slug' => 'wiki', 'with_front' => false],
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
