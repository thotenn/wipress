<?php
/**
 * Plugin Name: WiPress
 * Description: Wiki system for WordPress with hierarchical navigation, Markdown support, REST API and MCP server.
 * Version: 1.0.0
 * Author: Coding Partner
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('WIPRESS_VERSION', '1.0.6');
define('WIPRESS_PATH', plugin_dir_path(__FILE__));
define('WIPRESS_URL', plugin_dir_url(__FILE__));

// Includes
require_once WIPRESS_PATH . 'includes/class-post-type.php';
require_once WIPRESS_PATH . 'includes/class-walker-wiki.php';
require_once WIPRESS_PATH . 'includes/class-template.php';
require_once WIPRESS_PATH . 'includes/class-markdown.php';
require_once WIPRESS_PATH . 'includes/class-rest-api.php';
require_once WIPRESS_PATH . 'includes/class-mcp-server.php';
require_once WIPRESS_PATH . 'includes/class-import-export.php';

// Allow Application Passwords over HTTP in local dev
add_filter('wp_is_application_passwords_available', '__return_true');

// Init
Wipress_Post_Type::init();
Wipress_Template::init();
Wipress_Markdown::init();
Wipress_REST_API::init();
Wipress_MCP_Server::init();
Wipress_Import_Export::init();

// Register markdown block
add_action('init', function() {
    register_block_type(WIPRESS_PATH . 'blocks/markdown');
});

// Activation
register_activation_hook(__FILE__, function() {
    Wipress_Post_Type::register();
    Wipress_Post_Type::register_rewrite_rules();
    flush_rewrite_rules();
});
