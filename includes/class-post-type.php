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
        add_action('wiki_project_edit_form_fields', [__CLASS__, 'render_mcp_info_fields'], 10, 2);
        add_action('wiki_project_add_form_fields', [__CLASS__, 'render_visibility_add_field']);
        add_action('wiki_project_edit_form_fields', [__CLASS__, 'render_visibility_edit_field'], 3, 2);
        add_action('created_wiki_project', [__CLASS__, 'save_visibility_field']);
        add_action('edited_wiki_project', [__CLASS__, 'save_visibility_field']);
        add_action('wiki_project_edit_form_fields', [__CLASS__, 'render_import_export_fields'], 5, 2);
        add_filter('manage_edit-wiki_project_columns', [__CLASS__, 'add_project_columns']);
        add_filter('manage_wiki_project_custom_column', [__CLASS__, 'render_project_column'], 10, 3);
        add_action('admin_footer-edit-tags.php', [__CLASS__, 'render_import_export_scripts']);
        add_action('admin_footer-term.php', [__CLASS__, 'render_import_export_scripts']);
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
            'labels'       => [
                'name'                       => 'Projects',
                'singular_name'              => 'Project',
                'search_items'               => 'Search Projects',
                'all_items'                  => 'All Projects',
                'parent_item'                => 'Parent Project',
                'parent_item_colon'          => 'Parent Project:',
                'edit_item'                  => 'Edit Project',
                'view_item'                  => 'View Project',
                'update_item'                => 'Update Project',
                'add_new_item'               => 'Add New Project',
                'new_item_name'              => 'New Project Name',
                'not_found'                  => 'No projects found.',
                'no_terms'                   => 'No projects',
                'items_list_navigation'      => 'Projects list navigation',
                'items_list'                 => 'Projects list',
                'back_to_items'              => '&larr; Go to Projects',
            ],
            'hierarchical' => true,
            'show_in_rest' => true,
            'rewrite'      => ['slug' => 'wiki-project'],
        ]);

        register_taxonomy('wiki_section', 'wiki', [
            'labels'       => [
                'name'                       => 'Sections',
                'singular_name'              => 'Section',
                'search_items'               => 'Search Sections',
                'all_items'                  => 'All Sections',
                'parent_item'                => 'Parent Section',
                'parent_item_colon'          => 'Parent Section:',
                'edit_item'                  => 'Edit Section',
                'view_item'                  => 'View Section',
                'update_item'                => 'Update Section',
                'add_new_item'               => 'Add New Section',
                'new_item_name'              => 'New Section Name',
                'not_found'                  => 'No sections found.',
                'no_terms'                   => 'No sections',
                'items_list_navigation'      => 'Sections list navigation',
                'items_list'                 => 'Sections list',
                'back_to_items'              => '&larr; Go to Sections',
            ],
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

    // --- Project visibility ---

    public static function render_visibility_add_field($taxonomy) {
        ?>
        <div class="form-field">
            <label><input type="checkbox" name="wipress_public" value="1" checked /> Public project</label>
            <p class="description">Uncheck to make this project private. Private projects are only visible to editors and administrators.</p>
        </div>
        <?php
    }

    public static function render_visibility_edit_field($term, $taxonomy) {
        $public = get_term_meta($term->term_id, '_wipress_public', true);
        if ($public === '') $public = '1';
        ?>
        <tr class="form-field">
            <th scope="row">Visibility</th>
            <td>
                <label><input type="checkbox" name="wipress_public" value="1" <?php checked($public, '1'); ?> /> Public project</label>
                <p class="description">Uncheck to make this project private. Private projects are only visible to editors and administrators.</p>
            </td>
        </tr>
        <?php
    }

    public static function save_visibility_field($term_id) {
        if (!isset($_POST['_wpnonce'])) return;
        $public = isset($_POST['wipress_public']) ? '1' : '0';
        update_term_meta($term_id, '_wipress_public', $public);
    }

    // --- Import/Export UI ---

    public static function add_project_columns($columns) {
        $columns['wipress_actions'] = __('Import / Export');
        return $columns;
    }

    public static function render_project_column($content, $column_name, $term_id) {
        if ($column_name !== 'wipress_actions') return $content;

        $term = get_term($term_id, 'wiki_project');
        if (!$term || is_wp_error($term)) return $content;

        $export_url = rest_url('wipress/v1/projects/' . $term->slug . '/export');

        ob_start();
        ?>
        <button type="button" class="button button-small wipress-import-btn" data-slug="<?php echo esc_attr($term->slug); ?>" title="Import project">
            <span class="dashicons dashicons-upload" style="vertical-align: text-bottom; font-size: 16px; width: 16px; height: 16px;"></span>
        </button>
        <button type="button" class="button button-small wipress-export-btn" data-slug="<?php echo esc_attr($term->slug); ?>" data-url="<?php echo esc_url($export_url); ?>" title="Export project">
            <span class="dashicons dashicons-download" style="vertical-align: text-bottom; font-size: 16px; width: 16px; height: 16px;"></span>
        </button>
        <?php
        return ob_get_clean();
    }

    public static function render_import_export_fields($term, $taxonomy) {
        $export_url = rest_url('wipress/v1/projects/' . $term->slug . '/export');
        ?>
        <tr class="form-field">
            <td colspan="2" style="padding: 0;">
                <h2 style="border-top: 1px solid #c3c4c7; padding-top: 1.5em; margin-top: 1em;">Import / Export</h2>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label>Export</label></th>
            <td>
                <button type="button" class="button wipress-export-btn" data-slug="<?php echo esc_attr($term->slug); ?>" data-url="<?php echo esc_url($export_url); ?>">
                    <span class="dashicons dashicons-download" style="vertical-align: text-bottom; margin-right: 4px;"></span>
                    Download JSON
                </button>
                <p class="description">Export all sections and pages of this project as a JSON file.</p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label>Import</label></th>
            <td>
                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <input type="file" id="wipress-import-file" accept=".json,application/json" />
                    <label style="display: inline-flex; align-items: center; gap: 4px;">
                        <input type="checkbox" id="wipress-import-merge" /> Merge (keep existing pages)
                    </label>
                    <button type="button" class="button wipress-import-file-btn" data-slug="<?php echo esc_attr($term->slug); ?>" disabled>
                        <span class="dashicons dashicons-upload" style="vertical-align: text-bottom; margin-right: 4px;"></span>
                        Import
                    </button>
                </div>
                <p class="description" style="margin-top: 6px;">
                    Upload a WiPress JSON export file. By default, existing pages are <strong>replaced</strong>. Check "Merge" to keep existing pages and add new ones.
                </p>
                <div id="wipress-import-result" style="margin-top: 8px;"></div>
            </td>
        </tr>
        <?php
    }

    public static function render_import_export_scripts() {
        $screen = get_current_screen();
        if (!$screen || $screen->taxonomy !== 'wiki_project') return;
        $nonce = wp_create_nonce('wp_rest');
        $import_url = rest_url('wipress/v1/import');
        ?>
        <script>
        (function() {
            var nonce = <?php echo wp_json_encode($nonce); ?>;
            var importUrl = <?php echo wp_json_encode($import_url); ?>;

            // Export buttons
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.wipress-export-btn');
                if (!btn) return;
                e.preventDefault();
                var url = btn.dataset.url;
                var slug = btn.dataset.slug;
                btn.disabled = true;
                fetch(url)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
                        var a = document.createElement('a');
                        a.href = URL.createObjectURL(blob);
                        a.download = slug + '-export.json';
                        a.click();
                        URL.revokeObjectURL(a.href);
                    })
                    .catch(function(err) { alert('Export failed: ' + err.message); })
                    .finally(function() { btn.disabled = false; });
            });

            // Import buttons (list table — opens file picker)
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.wipress-import-btn');
                if (!btn) return;
                e.preventDefault();
                var slug = btn.dataset.slug;
                var input = document.createElement('input');
                input.type = 'file';
                input.accept = '.json,application/json';
                input.onchange = function() {
                    if (!input.files[0]) return;
                    if (!confirm('Import into "' + slug + '"? This will REPLACE all existing pages.')) return;
                    btn.disabled = true;
                    var reader = new FileReader();
                    reader.onload = function() {
                        try { var data = JSON.parse(reader.result); } catch(err) { alert('Invalid JSON file'); btn.disabled = false; return; }
                        fetch(importUrl + '?mode=replace', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json', 'X-WP-Nonce': nonce},
                            body: JSON.stringify(data)
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (res.code) { alert('Import error: ' + (res.message || res.code)); }
                            else { alert('Imported: ' + res.pages_created + ' pages in ' + res.sections_count + ' sections'); location.reload(); }
                        })
                        .catch(function(err) { alert('Import failed: ' + err.message); })
                        .finally(function() { btn.disabled = false; });
                    };
                    reader.readAsText(input.files[0]);
                };
                input.click();
            });

            // Import from edit page (file input + button)
            var fileInput = document.getElementById('wipress-import-file');
            var importBtn = document.querySelector('.wipress-import-file-btn');
            if (fileInput && importBtn) {
                fileInput.addEventListener('change', function() {
                    importBtn.disabled = !fileInput.files.length;
                });
                importBtn.addEventListener('click', function() {
                    if (!fileInput.files[0]) return;
                    var slug = importBtn.dataset.slug;
                    var merge = document.getElementById('wipress-import-merge');
                    var mode = (merge && merge.checked) ? 'merge' : 'replace';
                    if (mode === 'replace' && !confirm('This will DELETE all existing pages in "' + slug + '" before importing. Continue?')) return;
                    importBtn.disabled = true;
                    var resultDiv = document.getElementById('wipress-import-result');
                    resultDiv.innerHTML = '<em>Importing...</em>';
                    var reader = new FileReader();
                    reader.onload = function() {
                        try { var data = JSON.parse(reader.result); } catch(err) { resultDiv.innerHTML = '<div class="notice notice-error inline"><p>Invalid JSON file.</p></div>'; importBtn.disabled = false; return; }
                        fetch(importUrl + '?mode=' + mode, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json', 'X-WP-Nonce': nonce},
                            body: JSON.stringify(data)
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (res.code) {
                                resultDiv.innerHTML = '<div class="notice notice-error inline"><p>Error: ' + (res.message || res.code) + '</p></div>';
                            } else {
                                resultDiv.innerHTML = '<div class="notice notice-success inline"><p>Imported <strong>' + res.pages_created + '</strong> pages in <strong>' + res.sections_count + '</strong> sections (mode: ' + res.mode + ').</p></div>';
                            }
                        })
                        .catch(function(err) { resultDiv.innerHTML = '<div class="notice notice-error inline"><p>Import failed: ' + err.message + '</p></div>'; })
                        .finally(function() { importBtn.disabled = false; });
                    };
                    reader.readAsText(fileInput.files[0]);
                });
            }
        })();
        </script>
        <?php
    }

    // --- MCP Info ---

    public static function render_mcp_info_fields($term, $taxonomy) {
        $mcp_url = rest_url('wipress/v1/mcp/' . $term->slug);
        $site_url = get_site_url();

        $config_readonly = json_encode([
            'mcpServers' => [
                'wipress/' . $term->slug => [
                    'url' => $mcp_url,
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $config_readwrite = json_encode([
            'mcpServers' => [
                'wipress/' . $term->slug => [
                    'url' => $mcp_url,
                    'headers' => [
                        'Authorization' => 'Basic <base64 user:application_password>',
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $tools = self::get_mcp_tools_reference();
        ?>
        <tr class="form-field">
            <td colspan="2" style="padding: 0;">
                <h2 style="border-top: 1px solid #c3c4c7; padding-top: 1.5em; margin-top: 1em;">MCP Server</h2>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label>MCP Endpoint URL</label></th>
            <td>
                <input type="text" readonly value="<?php echo esc_url($mcp_url); ?>" onclick="this.select()" class="large-text" />
                <p class="description">Project-scoped MCP endpoint. Read tools are public, write tools require authentication.</p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label>Client Config (read-only)</label></th>
            <td>
                <textarea readonly rows="7" class="large-text code" onclick="this.select()"><?php echo esc_textarea($config_readonly); ?></textarea>
                <p class="description">Add this to your MCP client configuration for read-only access (no auth required).</p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label>Client Config (read/write)</label></th>
            <td>
                <textarea readonly rows="10" class="large-text code" onclick="this.select()"><?php echo esc_textarea($config_readwrite); ?></textarea>
                <p class="description">
                    Replace <code>&lt;base64 user:application_password&gt;</code> with your credentials.
                    Create an Application Password in <a href="<?php echo esc_url(admin_url('profile.php')); ?>">Users &rarr; Profile</a>.
                </p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label>Available Tools</label></th>
            <td>
                <table class="widefat striped" style="max-width: 800px;">
                    <thead>
                        <tr>
                            <th>Tool</th>
                            <th>Auth</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tools as $tool): ?>
                        <tr>
                            <td><code><?php echo esc_html($tool['name']); ?></code></td>
                            <td><?php echo $tool['write'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo esc_html($tool['description']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description" style="margin-top: 8px;">
                    Scoped endpoint exposes <?php echo count($tools); ?> tools. The <code>project</code> parameter is auto-injected.
                </p>
            </td>
        </tr>
        <?php
    }

    private static function get_mcp_tools_reference() {
        return [
            ['name' => 'wiki_list_sections',   'write' => false, 'description' => 'List sections in this project'],
            ['name' => 'wiki_get_tree',        'write' => false, 'description' => 'Get the full navigation tree for this project'],
            ['name' => 'wiki_list_pages',      'write' => false, 'description' => 'List wiki pages with optional filters'],
            ['name' => 'wiki_get_page',        'write' => false, 'description' => 'Get a wiki page by ID with full content'],
            ['name' => 'wiki_create_page',     'write' => true,  'description' => 'Create a new wiki page in this project'],
            ['name' => 'wiki_update_page',     'write' => true,  'description' => 'Update an existing wiki page'],
            ['name' => 'wiki_delete_page',     'write' => true,  'description' => 'Delete a wiki page permanently'],
            ['name' => 'wiki_move_page',       'write' => true,  'description' => 'Move a wiki page to a new parent or change its order'],
            ['name' => 'wiki_search',          'write' => false, 'description' => 'Search wiki content in this project'],
            ['name' => 'wiki_export_project',  'write' => false, 'description' => 'Export this project as a complete JSON structure'],
            ['name' => 'wiki_import_project',  'write' => true,  'description' => 'Import a wiki project from a JSON structure'],
        ];
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
