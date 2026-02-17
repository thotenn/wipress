<?php
get_header();

$projects = Wipress_REST_API::list_projects_internal();
?>

<div class="wdh-inf-container">
    <header class="wdh-inf-header">
        <div class="wdh-inf-logo">Wiki</div>
    </header>

    <div class="wdh-inf-grid">
        <main class="wdh-inf-content wdh-inf-content--full">
            <h1>Projects</h1>
            <?php if (!empty($projects)) : ?>
            <ul class="wdh-folder-listing">
                <?php foreach ($projects as $project) : ?>
                <li>
                    <a href="<?php echo esc_url(home_url('/wiki/' . $project['slug'] . '/')); ?>">
                        <?php echo esc_html($project['name']); ?>
                    </a>
                    <span class="wdh-folder-desc"><?php echo esc_html($project['count'] . ' pages'); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else : ?>
            <p>No projects found.</p>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php get_footer(); ?>
