<?php
if (!defined('ABSPATH')) exit;

class Wipress_Import_Export {

    public static function init() {
        // Nothing to hook for now — methods called from REST API and MCP
    }

    public static function export_project_internal($project_slug) {
        $project = get_term_by('slug', $project_slug, 'wiki_project');
        if (!$project || !Wipress_REST_API::is_project_visible($project)) {
            return new WP_Error('not_found', 'Project not found: ' . $project_slug, ['status' => 404]);
        }

        $sections = Wipress_REST_API::list_sections_internal($project_slug);
        $export_sections = [];

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

            $export_sections[] = [
                'slug'  => $section['slug'],
                'name'  => $section['name'],
                'pages' => self::build_export_tree($posts),
            ];
        }

        return [
            'wipress_version' => WIPRESS_VERSION,
            'exported_at'     => gmdate('c'),
            'project'         => [
                'slug' => $project->slug,
                'name' => $project->name,
            ],
            'sections' => $export_sections,
        ];
    }

    public static function import_project_internal($data, $mode = 'replace') {
        $error = self::validate_import_data($data);
        if (is_wp_error($error)) return $error;

        $project_slug = sanitize_title($data['project']['slug']);
        $project_name = sanitize_text_field($data['project']['name']);

        // Create or get project term
        $term = term_exists($project_slug, 'wiki_project');
        if (!$term) {
            $term = wp_insert_term($project_name, 'wiki_project', ['slug' => $project_slug]);
        }
        if (is_wp_error($term)) return $term;

        // Replace mode: delete existing pages
        if ($mode === 'replace') {
            self::delete_project_pages($project_slug);
        }

        $pages_created = 0;

        foreach ($data['sections'] as $section_data) {
            $section_slug = sanitize_title($section_data['slug'] ?? $section_data['name']);
            $section_name = sanitize_text_field($section_data['name']);

            // Create or get section term
            $section_term = term_exists($section_slug, 'wiki_section');
            if (!$section_term) {
                $section_term = wp_insert_term($section_name, 'wiki_section', ['slug' => $section_slug]);
            }

            $pages = $section_data['pages'] ?? [];
            $pages_created += self::import_page_tree($pages, 0, $project_slug, $section_slug);
        }

        return [
            'project'        => $project_slug,
            'sections_count' => count($data['sections']),
            'pages_created'  => $pages_created,
            'mode'           => $mode,
        ];
    }

    private static function import_page_tree($pages, $parent_id, $project_slug, $section_slug) {
        $count = 0;

        foreach ($pages as $page) {
            // wp_slash() counteracts wp_insert_post's internal wp_unslash(),
            // preserving backslashes in Gutenberg block comments (JSON attributes)
            $post_id = wp_insert_post([
                'post_type'    => 'wiki',
                'post_title'   => wp_slash(sanitize_text_field($page['title'] ?? '')),
                'post_name'    => sanitize_title($page['slug'] ?? ''),
                'post_content' => wp_slash($page['content'] ?? ''),
                'post_status'  => 'publish',
                'post_parent'  => $parent_id,
                'menu_order'   => (int)($page['menu_order'] ?? 0),
            ], true);

            if (is_wp_error($post_id)) continue;

            $format = sanitize_text_field($page['content_format'] ?? 'html');
            update_post_meta($post_id, '_wipress_content_format', $format);

            Wipress_REST_API::set_taxonomies($post_id, [
                'project' => $project_slug,
                'section' => $section_slug,
            ]);

            $count++;

            if (!empty($page['children'])) {
                $count += self::import_page_tree($page['children'], $post_id, $project_slug, $section_slug);
            }
        }

        return $count;
    }

    private static function delete_project_pages($project_slug) {
        $posts = get_posts([
            'post_type'      => 'wiki',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => 'any',
            'tax_query'      => [
                ['taxonomy' => 'wiki_project', 'field' => 'slug', 'terms' => $project_slug],
            ],
        ]);

        foreach ($posts as $id) {
            wp_delete_post($id, true);
        }
    }

    private static function build_export_tree($posts, $parent_id = 0) {
        $branch = [];
        foreach ($posts as $p) {
            if ((int)$p->post_parent !== $parent_id) continue;
            $branch[] = [
                'title'          => $p->post_title,
                'slug'           => $p->post_name,
                'menu_order'     => $p->menu_order,
                'content'        => $p->post_content,
                'content_format' => get_post_meta($p->ID, '_wipress_content_format', true) ?: 'html',
                'children'       => self::build_export_tree($posts, $p->ID),
            ];
        }
        return $branch;
    }

    private static function validate_import_data($data) {
        if (empty($data['project']['slug'])) {
            return new WP_Error('invalid_data', 'Missing project.slug', ['status' => 400]);
        }
        if (empty($data['project']['name'])) {
            return new WP_Error('invalid_data', 'Missing project.name', ['status' => 400]);
        }
        if (!isset($data['sections']) || !is_array($data['sections'])) {
            return new WP_Error('invalid_data', 'Missing or invalid sections array', ['status' => 400]);
        }
        return true;
    }
}
