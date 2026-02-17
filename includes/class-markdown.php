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
            self::$parsedown->setSafeMode(true);
        }
        return self::$parsedown;
    }

    public static function render($markdown) {
        return self::get_parsedown()->text($markdown);
    }

    public static function filter_content($content) {
        if (get_post_type() !== 'wiki') return $content;
        if (get_post_meta(get_the_ID(), '_wipress_content_format', true) !== 'markdown') return $content;

        return self::render($content);
    }
}
