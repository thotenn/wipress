<?php
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
