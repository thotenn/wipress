<?php
if (!defined('ABSPATH')) exit;

class Wipress_Walker extends Walker {

    public $db_fields = [
        'parent' => 'post_parent',
        'id'     => 'ID',
    ];

    private $current_post_id = 0;
    private $ancestors = [];

    public function __construct($current_post_id = 0) {
        $this->current_post_id = $current_post_id;
        if ($current_post_id) {
            $this->ancestors = get_post_ancestors($current_post_id);
        }
    }

    public function start_lvl(&$output, $depth = 0, $args = []) {
        $indent = str_repeat("\t", $depth);
        $output .= "$indent<ul class=\"wdh-tree-children\">\n";
    }

    public function end_lvl(&$output, $depth = 0, $args = []) {
        $indent = str_repeat("\t", $depth);
        $output .= "$indent</ul>\n";
    }

    public function start_el(&$output, $post, $depth = 0, $args = [], $current_object_id = 0) {
        $classes = [];
        $has_children = !empty($args['has_children']);
        $is_active = ($post->ID === $this->current_post_id);
        $is_ancestor = in_array($post->ID, $this->ancestors);
        $is_folder = $has_children && empty(trim($post->post_content));

        if ($has_children) $classes[] = 'has-children';
        if ($is_folder) $classes[] = 'is-folder';
        if ($is_active) $classes[] = 'active';
        if ($is_ancestor) $classes[] = 'ancestor';

        $expanded = ($is_active || $is_ancestor) ? 'expanded' : 'collapsed';
        if ($has_children) $classes[] = $expanded;

        $class_str = implode(' ', $classes);
        $indent = str_repeat("\t", $depth);

        $output .= "$indent<li class=\"$class_str\">";

        if ($has_children) {
            $output .= '<button class="wdh-tree-toggle" aria-expanded="' . ($expanded === 'expanded' ? 'true' : 'false') . '">';
            $output .= '<svg class="wdh-chevron" width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M6 4l4 4-4 4"/></svg>';
            $output .= '</button>';
        }

        if ($is_folder) {
            $output .= '<span class="wdh-tree-folder" role="button" tabindex="0">' . esc_html(get_the_title($post)) . '</span>';
        } else {
            $output .= '<a href="' . esc_url(get_permalink($post)) . '">' . esc_html(get_the_title($post)) . '</a>';
        }
    }

    public function end_el(&$output, $post, $depth = 0, $args = []) {
        $output .= "</li>\n";
    }

    public static function render_tree($posts, $current_post_id) {
        if (empty($posts)) return '';

        $walker = new self($current_post_id);
        return '<ul class="wdh-inf-tree">' . $walker->walk($posts, 0) . '</ul>';
    }
}
