<?php
if (!defined('ABSPATH')) exit;

class Wipress_Template {

    public static function init() {
        add_filter('template_include', [__CLASS__, 'load_template']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function load_template($template) {
        if (get_query_var('wipress_not_found')) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return $template;
        }

        if (get_query_var('wipress_archive')) {
            return WIPRESS_PATH . 'templates/archive-wiki.php';
        }

        $project_slug = get_query_var('wipress_project_slug');
        if ($project_slug && !is_singular('wiki')) {
            $term = get_term_by('slug', $project_slug, 'wiki_project');
            if ($term && Wipress_REST_API::is_project_visible($term)) {
                $first_page = self::get_project_first_page($term->term_id);
                if ($first_page) {
                    wp_redirect(get_permalink($first_page), 302);
                    exit;
                }
            }
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return $template;
        }

        if (is_singular('wiki')) {
            $post_id = get_queried_object_id();
            $terms = wp_get_object_terms($post_id, 'wiki_project');
            if (!empty($terms) && !Wipress_REST_API::is_project_visible($terms[0])) {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                return $template;
            }
            return WIPRESS_PATH . 'templates/single-wiki.php';
        }
        return $template;
    }

    public static function get_project_first_page($term_id) {
        $posts = get_posts([
            'post_type'      => 'wiki',
            'posts_per_page' => 1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'post_status'    => 'publish',
            'post_parent'    => 0,
            'tax_query'      => [
                ['taxonomy' => 'wiki_project', 'field' => 'term_id', 'terms' => $term_id],
            ],
        ]);
        return !empty($posts) ? $posts[0] : null;
    }

    public static function enqueue_assets() {
        if (!is_singular('wiki') && !is_post_type_archive('wiki') && !get_query_var('wipress_archive')) return;

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
