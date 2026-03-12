<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$render_post = null;
if (have_posts()) {
    the_post();
    $render_post = get_post(get_the_ID());
} else {
    $render_post = get_post((int) get_queried_object_id());
    if (!($render_post instanceof WP_Post)) {
        global $post;
        if ($post instanceof WP_Post) {
            $render_post = $post;
        }
    }
}

if ($render_post instanceof WP_Post) {
    setup_postdata($render_post);
    $html = SML_Pages_Plugin::get_rendered_for_post((int) $render_post->ID);
    $wrapper_style = SML_Pages_Plugin::get_theme_wrapper_style_for_post((int) $render_post->ID);
    if ($wrapper_style !== '') {
        echo '<style id="sml-wrapper-style">#wrapper{' . esc_html($wrapper_style) . '}</style>';
    }
    ?>
<main class="container sml-page-shell">
    <article>
        <header>
            <h1><?php echo esc_html(get_the_title($render_post)); ?></h1>
        </header>
        <section class="sml-render-root">
            <?php echo wp_kses_post($html); ?>
        </section>
    </article>
</main>
    <?php
    wp_reset_postdata();
}

get_footer();
