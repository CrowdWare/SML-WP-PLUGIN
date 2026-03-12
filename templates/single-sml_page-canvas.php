<?php
if (!defined('ABSPATH')) {
    exit;
}

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
    ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php wp_head(); ?>
</head>
<body <?php body_class('sml-canvas-mode'); ?>>
<?php wp_body_open(); ?>

<main class="sml-canvas-root" aria-label="SML Canvas">
    <?php echo wp_kses_post($html); ?>
</main>

<?php wp_footer(); ?>
</body>
</html>
    <?php
    wp_reset_postdata();
}
