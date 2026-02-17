<?php
if (!defined('ABSPATH')) exit;

class Wipress_Template {

    public static function init() {
        add_filter('template_include', [__CLASS__, 'load_template']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function load_template($template) {
        if (is_singular('wiki')) {
            return WIPRESS_PATH . 'templates/single-wiki.php';
        }
        return $template;
    }

    public static function enqueue_assets() {
        if (!is_singular('wiki') && !is_post_type_archive('wiki')) return;

        wp_enqueue_style('wipress-style', WIPRESS_URL . 'assets/style.css', [], WIPRESS_VERSION);
        wp_enqueue_script('wipress-js', WIPRESS_URL . 'assets/script.js', [], WIPRESS_VERSION, true);
    }

    public static function get_sidebar_posts($project_id, $section_id) {
        return get_posts([
            'post_type'      => 'wiki',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'post_status'    => 'publish',
            'tax_query'      => [
                'relation' => 'AND',
                ['taxonomy' => 'wiki_project', 'field' => 'term_id', 'terms' => $project_id],
                ['taxonomy' => 'wiki_section', 'field' => 'term_id', 'terms' => $section_id],
            ],
        ]);
    }

    public static function get_project_sections($project_id) {
        $sections = [];
        $all_sections = get_terms(['taxonomy' => 'wiki_section', 'hide_empty' => false]);

        foreach ($all_sections as $s) {
            $check = get_posts([
                'post_type'      => 'wiki',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'tax_query'      => [
                    'relation' => 'AND',
                    ['taxonomy' => 'wiki_project', 'field' => 'term_id', 'terms' => $project_id],
                    ['taxonomy' => 'wiki_section', 'field' => 'term_id', 'terms' => $s->term_id],
                ],
            ]);
            if (!empty($check)) {
                $sections[] = [
                    'term'      => $s,
                    'first_url' => get_permalink($check[0]->ID),
                ];
            }
        }
        return $sections;
    }
}
