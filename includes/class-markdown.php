<?php
if (!defined('ABSPATH')) exit;

class Wipress_Markdown {

    private static $parsedown = null;

    public static function init() {
        add_filter('the_content', [__CLASS__, 'filter_content'], 5);
    }

    public static function get_parsedown() {
        if (self::$parsedown === null) {
            require_once WIPRESS_PATH . 'vendor/Parsedown.php';
            self::$parsedown = new Parsedown();
            self::$parsedown->setMarkupEscaped(false);
        }
        return self::$parsedown;
    }

    public static function render($markdown) {
        $html = self::get_parsedown()->text($markdown);
        $html = wp_kses_post($html);
        return self::add_image_loading_attrs($html);
    }

    /**
     * Add lazy-loading hints to <img> tags that don't already declare a loading
     * attribute. The_content path gets this from core, but the block render path does not.
     */
    private static function add_image_loading_attrs($html) {
        if (strpos($html, '<img') === false) return $html;
        return preg_replace_callback('/<img\b(?![^>]*\bloading=)([^>]*)>/i', function($m) {
            return '<img loading="lazy" decoding="async"' . $m[1] . '>';
        }, $html);
    }

    public static function filter_content($content) {
        if (get_post_type() !== 'wiki') return $content;
        if (get_post_meta(get_the_ID(), '_wipress_content_format', true) !== 'markdown') return $content;

        return self::render($content);
    }
}
