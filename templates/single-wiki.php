<?php
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
    (function(){
        var t=localStorage.getItem('wipress-theme');
        if(!t)t=window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light';
        document.documentElement.setAttribute('data-theme',t);
    })();
    </script>
    <?php wp_head(); ?>
</head>
<body <?php body_class('wipress-standalone'); ?>>
<?php wp_body_open();

$current_post_id = get_the_ID();
$project_terms = get_the_terms($current_post_id, 'wiki_project');
$section_terms = get_the_terms($current_post_id, 'wiki_section');
$project = $project_terms ? $project_terms[0] : null;
$section = $section_terms ? $section_terms[0] : null;

$sections = $project ? Wipress_Template::get_project_sections($project->term_id) : [];
$sidebar_posts = ($project && $section) ? Wipress_Template::get_sidebar_posts($project->term_id, $section->term_id) : [];
?>

<div class="wdh-inf-container">
    <?php if ($project) : ?>
    <header class="wdh-inf-header">
        <div class="wdh-inf-header-left">
            <a href="<?php echo esc_url(home_url('/wiki/' . $project->slug . '/')); ?>" class="wdh-inf-logo"><?php echo esc_html($project->name); ?></a>
            <nav class="wdh-inf-tabs">
                <?php foreach ($sections as $s) :
                    $active = ($section && $s['term']->term_id === $section->term_id) ? 'is-active' : '';
                ?>
                    <a href="<?php echo esc_url($s['first_url']); ?>" class="<?php echo $active; ?>">
                        <?php echo esc_html($s['term']->name); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="wdh-inf-site-title"><?php echo esc_html(get_bloginfo('name')); ?></a>
        <div class="wdh-inf-header-actions">
            <button type="button" class="wdh-header-btn" id="wdh-btn-download-md"
                    data-tooltip="Download as Markdown"
                    data-api-url="<?php echo esc_url(rest_url('wipress/v1/pages/' . $current_post_id)); ?>"
                    data-page-slug="<?php echo esc_attr(get_post()->post_name); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
            </button>
            <button type="button" class="wdh-header-btn" id="wdh-btn-copy-mcp"
                    data-tooltip="Copy MCP URL"
                    data-mcp-url="<?php echo esc_url(rest_url('wipress/v1/mcp/' . $project->slug)); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="4 17 10 11 4 5"/>
                    <line x1="12" y1="19" x2="20" y2="19"/>
                </svg>
            </button>
            <button type="button" class="wdh-header-btn" id="wdh-btn-theme-toggle" data-tooltip="Toggle theme">
                <svg class="wdh-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"/>
                    <line x1="12" y1="1" x2="12" y2="3"/>
                    <line x1="12" y1="21" x2="12" y2="23"/>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                    <line x1="1" y1="12" x2="3" y2="12"/>
                    <line x1="21" y1="12" x2="23" y2="12"/>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>
                <svg class="wdh-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </button>
        </div>
    </header>
    <?php endif; ?>

    <div class="wdh-inf-grid">
        <aside class="wdh-inf-sidebar-left">
            <?php echo Wipress_Walker::render_tree($sidebar_posts, $current_post_id); ?>
        </aside>

        <main class="wdh-inf-content">
            <article>
                <h1><?php the_title(); ?></h1>
                <?php if (empty(trim(get_post()->post_content))) :
                    $children = get_posts([
                        'post_parent'    => $current_post_id,
                        'post_type'      => 'wiki',
                        'post_status'    => 'publish',
                        'orderby'        => 'menu_order',
                        'order'          => 'ASC',
                        'posts_per_page' => -1,
                    ]);
                    if (!empty($children)) : ?>
                    <ul class="wdh-folder-listing">
                        <?php foreach ($children as $child) : ?>
                        <li>
                            <a href="<?php echo esc_url(get_permalink($child)); ?>">
                                <?php echo esc_html($child->post_title); ?>
                            </a>
                            <?php if ($child->post_excerpt) : ?>
                                <span class="wdh-folder-desc"><?php echo esc_html($child->post_excerpt); ?></span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="wdh-render"><?php the_content(); ?></div>
                <?php endif; ?>
            </article>
        </main>

        <aside class="wdh-inf-sidebar-right">
            <div class="wdh-inf-toc">
                <span class="toc-label">On this page</span>
                <div id="wdh-toc-js"></div>
            </div>
        </aside>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
