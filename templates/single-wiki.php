<?php
// Redirect folder pages (no content + has children) to first child
$current_post = get_post();
if ($current_post && empty(trim($current_post->post_content))) {
    $children = get_children([
        'post_parent' => $current_post->ID,
        'post_type'   => 'wiki',
        'post_status' => 'publish',
        'orderby'     => 'menu_order',
        'order'       => 'ASC',
        'numberposts' => 1,
    ]);
    if (!empty($children)) {
        $first_child = reset($children);
        wp_redirect(get_permalink($first_child->ID), 302);
        exit;
    }
}

get_header();

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
        <div class="wdh-inf-logo"><?php echo esc_html($project->name); ?></div>
        <nav class="wdh-inf-tabs">
            <?php foreach ($sections as $s) :
                $active = ($section && $s['term']->term_id === $section->term_id) ? 'is-active' : '';
            ?>
                <a href="<?php echo esc_url($s['first_url']); ?>" class="<?php echo $active; ?>">
                    <?php echo esc_html($s['term']->name); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>
    <?php endif; ?>

    <div class="wdh-inf-grid">
        <aside class="wdh-inf-sidebar-left">
            <?php echo Wipress_Walker::render_tree($sidebar_posts, $current_post_id); ?>
        </aside>

        <main class="wdh-inf-content">
            <article>
                <h1><?php the_title(); ?></h1>
                <div class="wdh-render"><?php the_content(); ?></div>
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

<?php get_footer(); ?>
