<?php
if (!defined('ABSPATH')) exit;

class Wipress_Post_Type {

    public static function init() {
        add_action('init', [__CLASS__, 'register']);
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
    }
}
