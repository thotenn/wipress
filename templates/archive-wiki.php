<?php
?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="wipress-page">
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

$projects = Wipress_REST_API::list_projects_internal();
?>

<div class="wdh-inf-container">
    <header class="wdh-inf-header">
        <div class="wdh-inf-logo"><?php esc_html_e('Wiki', 'wipress'); ?></div>
    </header>

    <div class="wdh-inf-grid">
        <main class="wdh-inf-content wdh-inf-content--full">
            <h1><?php esc_html_e('Projects', 'wipress'); ?></h1>
            <?php if (!empty($projects)) : ?>
            <ul class="wdh-folder-listing">
                <?php foreach ($projects as $project) : ?>
                <li>
                    <a href="<?php echo esc_url(home_url('/wiki/' . $project['slug'] . '/')); ?>">
                        <?php echo esc_html($project['name']); ?>
                    </a>
                    <span class="wdh-folder-desc"><?php
                        /* translators: %d: number of wiki pages in the project */
                        echo esc_html(sprintf(_n('%d page', '%d pages', $project['count'], 'wipress'), $project['count']));
                    ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else : ?>
            <p><?php esc_html_e('No projects found.', 'wipress'); ?></p>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
