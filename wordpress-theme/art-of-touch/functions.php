<?php

if (!defined('ABSPATH')) {
    exit;
}

function aot_theme_version(): string
{
    $style_file = get_template_directory() . '/style.css';
    return (string) filemtime($style_file);
}

function aot_setup_theme(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);

    register_nav_menus([
        'primary' => __('Primary Menu', 'art-of-touch'),
    ]);
}
add_action('after_setup_theme', 'aot_setup_theme');

function aot_enqueue_assets(): void
{
    $theme_uri = get_template_directory_uri();
    $version = aot_theme_version();

    wp_enqueue_style('aot-google-fonts', 'https://fonts.googleapis.com/css?family=Open+Sans:300,400,700,800', [], null);

    wp_enqueue_style('aot-bootstrap', $theme_uri . '/assets/plugins/bootstrap/css/bootstrap.min.css', [], $version);
    wp_enqueue_style('aot-font-awesome', $theme_uri . '/assets/css/font-awesome.css', ['aot-bootstrap'], $version);
    wp_enqueue_style('aot-owl-carousel', $theme_uri . '/assets/plugins/owl-carousel/owl.carousel.css', ['aot-bootstrap'], $version);
    wp_enqueue_style('aot-owl-theme', $theme_uri . '/assets/plugins/owl-carousel/owl.theme.css', ['aot-owl-carousel'], $version);
    wp_enqueue_style('aot-owl-transitions', $theme_uri . '/assets/plugins/owl-carousel/owl.transitions.css', ['aot-owl-theme'], $version);
    wp_enqueue_style('aot-magnific-popup', $theme_uri . '/assets/plugins/magnific-popup/magnific-popup.css', [], $version);
    wp_enqueue_style('aot-animate', $theme_uri . '/assets/css/animate.css', [], $version);
    wp_enqueue_style('aot-superslides', $theme_uri . '/assets/css/superslides.css', [], $version);
    wp_enqueue_style('aot-blog', $theme_uri . '/assets/css/blog.css', [], $version);
    wp_enqueue_style('aot-essentials', $theme_uri . '/assets/css/essentials.css', [], $version);
    wp_enqueue_style('aot-layout', $theme_uri . '/assets/css/layout.css', ['aot-essentials'], $version);
    wp_enqueue_style('aot-layout-responsive', $theme_uri . '/assets/css/layout-responsive.css', ['aot-layout'], $version);
    wp_enqueue_style('aot-color-scheme', $theme_uri . '/assets/css/color_scheme/orange.css', ['aot-layout'], $version);
    wp_enqueue_style('aot-style', $theme_uri . '/assets/css/style.css', ['aot-layout-responsive'], $version);
    wp_enqueue_style('aot-theme-style', get_stylesheet_uri(), ['aot-style'], $version);

    wp_enqueue_style('aot-rev-slider', $theme_uri . '/assets/plugins/revolution-slider/css/settings.css', [], $version);

    wp_enqueue_script('aot-modernizr', $theme_uri . '/assets/plugins/modernizr.min.js', [], $version, false);

    // The original Atropos scripts rely on legacy jQuery behavior (window.load shortcut, etc.).
    // Use the project's bundled jQuery to keep frontend widgets (isotope/masonry) stable.
    wp_deregister_script('jquery');
    wp_register_script('jquery', $theme_uri . '/assets/plugins/jquery-2.0.3.min.js', [], $version, false);
    wp_enqueue_script('jquery');
    wp_enqueue_script('aot-easing', $theme_uri . '/assets/plugins/jquery.easing.1.3.js', ['jquery'], $version, true);
    wp_enqueue_script('aot-cookie', $theme_uri . '/assets/plugins/jquery.cookie.js', ['jquery'], $version, true);
    wp_enqueue_script('aot-appear', $theme_uri . '/assets/plugins/jquery.appear.js', ['jquery'], $version, true);
    wp_enqueue_script('aot-isotope', $theme_uri . '/assets/plugins/jquery.isotope.js', ['jquery'], $version, true);
    wp_enqueue_script('aot-masonry', $theme_uri . '/assets/plugins/masonry.js', ['jquery'], $version, true);

    wp_enqueue_script('aot-bootstrap', $theme_uri . '/assets/plugins/bootstrap/js/bootstrap.min.js', ['jquery'], $version, true);
    wp_enqueue_script('aot-magnific-popup', $theme_uri . '/assets/plugins/magnific-popup/jquery.magnific-popup.min.js', ['jquery'], $version, true);
    wp_enqueue_script('aot-owl-carousel', $theme_uri . '/assets/plugins/owl-carousel/owl.carousel.min.js', ['jquery'], $version, true);
    wp_enqueue_script('aot-stellar', $theme_uri . '/assets/plugins/stellar/jquery.stellar.min.js', ['jquery'], $version, true);
    wp_enqueue_script('aot-knob', $theme_uri . '/assets/plugins/knob/js/jquery.knob.js', ['jquery'], $version, true);
    wp_enqueue_script('aot-backstretch', $theme_uri . '/assets/plugins/jquery.backstretch.min.js', ['jquery'], $version, true);
    wp_enqueue_script('aot-superslides', $theme_uri . '/assets/plugins/superslides/dist/jquery.superslides.min.js', ['jquery'], $version, true);
    wp_enqueue_script('aot-mediaelement', $theme_uri . '/assets/plugins/mediaelement/build/mediaelement-and-player.min.js', ['jquery'], $version, true);

    wp_enqueue_script('aot-rev-plugins', $theme_uri . '/assets/plugins/revolution-slider/js/jquery.themepunch.plugins.min.js', ['jquery'], $version, true);
    wp_enqueue_script('aot-rev-main', $theme_uri . '/assets/plugins/revolution-slider/js/jquery.themepunch.revolution.min.js', ['jquery', 'aot-rev-plugins'], $version, true);
    wp_enqueue_script('aot-rev-init', $theme_uri . '/assets/js/slider_revolution.js', ['jquery', 'aot-rev-main'], $version, true);

    wp_enqueue_script('aot-scripts', $theme_uri . '/assets/js/scripts.js', ['jquery'], $version, true);

    $inline_js = <<<'JS'
function validateEmail() {
    var email = document.getElementById("email");
    var button = document.getElementById("verify-btn");
    if (!email || !button) {
        return;
    }

    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    button.disabled = !emailRegex.test(email.value);
}

function validatePassword() {
    var pwdField = document.getElementById("pwd");
    if (!pwdField) {
        return;
    }

    var passwordRegex = /^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
    if (!passwordRegex.test(pwdField.value)) {
        pwdField.setCustomValidity("Password must be at least 8 characters long, include 1 uppercase letter, 1 number, and 1 special character.");
    } else {
        pwdField.setCustomValidity("");
    }
}

function validateDOB() {
    var dobField = document.getElementById("dob");
    if (!dobField || !dobField.value) {
        return;
    }

    var today = new Date();
    var birthDate = new Date(dobField.value);
    var age = today.getFullYear() - birthDate.getFullYear();
    var monthDiff = today.getMonth() - birthDate.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }

    if (age < 18) {
        dobField.setCustomValidity("You must be at least 18 years old.");
    } else {
        dobField.setCustomValidity("");
    }
}

function sendVerificationCode(website) {
    var nameField = document.getElementById('name');
    var emailField = document.getElementById('email');

    if (!nameField || !nameField.value) {
        alert('Please enter a name first.');
        return;
    }
    if (!emailField || !emailField.value) {
        alert('Please enter an email address first.');
        return;
    }

    fetch('https://artanidos.pythonanywhere.com/nocode/getverificationcode', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: nameField.value,
            email: emailField.value,
            locale: 'en',
            website: website
        })
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        alert((data && data.message) ? data.message : 'Verification code sent!');
    })
    .catch(function() {
        alert('Error sending verification code.');
    });
}
JS;

    wp_add_inline_script('aot-modernizr', $inline_js, 'after');

    wp_enqueue_script('aot-umami', 'https://cloud.umami.is/script.js', [], null, false);
    wp_script_add_data('aot-umami', 'defer', true);

    wp_enqueue_script(
        'aot-mailchimp',
        'https://chimpstatic.com/mcjs-connected/js/users/12db9814ff19f2466f75cf6f4/10e2cd99169fa54535215a471.js',
        [],
        null,
        false
    );
}
add_action('wp_enqueue_scripts', 'aot_enqueue_assets');

function aot_script_loader_tag(string $tag, string $handle, string $src): string
{
    if ($handle === 'aot-umami') {
        return '<script defer src="' . esc_url($src) . '" data-website-id="dce0d047-c3ba-4828-aba5-2ebace760903"></script>' . "\n";
    }

    return $tag;
}
add_filter('script_loader_tag', 'aot_script_loader_tag', 10, 3);

function aot_redirect_legacy_html_urls(): void
{
    if (is_admin()) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if ($request_uri === '') {
        return;
    }

    $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
    if (!preg_match('#/([a-z0-9\-_]+)\.html$#i', $path, $matches)) {
        return;
    }

    $base = strtolower($matches[1]);
    if ($base === 'index') {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }

    $slug_map = get_option('aot_slug_map', []);
    $slug = (is_array($slug_map) && isset($slug_map[$base]))
        ? (string) $slug_map[$base]
        : sanitize_title($base);

    wp_safe_redirect(home_url('/' . trim($slug, '/') . '/'), 301);
    exit;
}
add_action('template_redirect', 'aot_redirect_legacy_html_urls', 1);

function aot_fallback_primary_menu(array $args): void
{
    $args = wp_parse_args($args, [
        'menu_class' => 'nav nav-pills nav-main scroll-menu',
        'menu_id' => 'topMain',
    ]);

    echo '<ul class="' . esc_attr($args['menu_class']) . '" id="' . esc_attr($args['menu_id']) . '">';
    wp_list_pages([
        'title_li' => '',
        'depth' => 1,
    ]);
    echo '</ul>';
}

function aot_rewrite_content_urls(string $content): string
{
    if (is_admin()) {
        return $content;
    }

    return preg_replace_callback(
        '/\b(href|src)=(["\'])([^"\']+)\2/i',
        static function (array $matches): string {
            $attr = $matches[1];
            $quote = $matches[2];
            $url = $matches[3];

            if (
                $url === '' ||
                strpos($url, '#') === 0 ||
                strpos($url, 'mailto:') === 0 ||
                strpos($url, 'tel:') === 0 ||
                strpos($url, 'data:') === 0 ||
                strpos($url, 'javascript:') === 0 ||
                preg_match('#^(https?:)?//#i', $url)
            ) {
                return $matches[0];
            }

            $rewritten = aot_map_legacy_url_to_wordpress($url);
            return $attr . '=' . $quote . esc_attr($rewritten) . $quote;
        },
        $content
    );
}
add_filter('the_content', 'aot_rewrite_content_urls', 20);

function aot_map_legacy_url_to_wordpress(string $url): string
{
    $url = preg_replace('#^\./#', '', $url);
    $url = ltrim($url, '/');

    if (strpos($url, 'assets/') === 0) {
        return trailingslashit(get_template_directory_uri()) . $url;
    }

    if (preg_match('#^index\.html(#.*)?$#i', $url, $matches)) {
        $anchor = $matches[1] ?? '';
        if ($anchor !== '') {
            return home_url('/') . $anchor;
        }
        return home_url('/');
    }

    if (preg_match('#^([a-z0-9\-_]+)\.html(#.*)?$#i', $url, $matches)) {
        $base = strtolower($matches[1]);
        $anchor = $matches[2] ?? '';
        $slug_map = get_option('aot_slug_map', []);

        $target = '';
        if (is_array($slug_map) && isset($slug_map[$base])) {
            $target = home_url('/' . trim($slug_map[$base], '/') . '/');
        } else {
            // Fallback if slug map is missing: still generate absolute WP URL.
            $target = home_url('/' . sanitize_title($base) . '/');
        }

        if ($anchor !== '') {
            $target .= $anchor;
        }
        return $target;
    }

    return $url;
}

function aot_render_page_content(int $post_id): string
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return '';
    }

    $content = (string) $post->post_content;
    $is_imported = (bool) get_post_meta($post_id, '_aot_source_file', true);

    if ($is_imported) {
        // Imported legacy HTML must not pass through wpautop; it breaks complex markup.
        $content = do_shortcode($content);
        $content = aot_rewrite_content_urls($content);
        if (trim($content) === '') {
            $fallback = aot_get_seed_content_for_post($post);
            if ($fallback !== '') {
                return aot_rewrite_content_urls(do_shortcode($fallback));
            }
        }
        return $content;
    }

    if (trim($content) === '') {
        $fallback = aot_get_seed_content_for_post($post);
        if ($fallback !== '') {
            return aot_rewrite_content_urls(do_shortcode($fallback));
        }
    }

    return apply_filters('the_content', $content);
}

function aot_get_seed_content_for_post(WP_Post $post): string
{
    $source_file = (string) get_post_meta((int) $post->ID, '_aot_source_file', true);
    if ($source_file !== '') {
        $content = aot_get_seed_content_by_filename($source_file);
        if ($content !== '') {
            return $content;
        }
    }

    $basename = ($post->post_name === 'home') ? 'index' : $post->post_name;
    return aot_get_seed_content_by_filename($basename . '.html');
}

function aot_get_seed_content_by_filename(string $filename): string
{
    $seed_dir = trailingslashit(get_template_directory()) . 'seed/';
    $path = $seed_dir . ltrim($filename, '/');

    if (!is_file($path) || !is_readable($path)) {
        return '';
    }

    $html = file_get_contents($path);
    if ($html === false) {
        return '';
    }

    return aot_extract_content_from_html($html);
}

function aot_add_import_page(): void
{
    add_management_page(
        __('Art of Touch Import', 'art-of-touch'),
        __('Art of Touch Import', 'art-of-touch'),
        'manage_options',
        'aot-import',
        'aot_render_import_page'
    );
}
add_action('admin_menu', 'aot_add_import_page');

function aot_render_import_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $status = isset($_GET['aot_import']) ? sanitize_text_field(wp_unslash($_GET['aot_import'])) : '';
    $count = isset($_GET['count']) ? (int) $_GET['count'] : 0;
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Art of Touch HTML Import', 'art-of-touch'); ?></h1>
        <p><?php esc_html_e('Imports all pages from theme/seed/*.html into editable WordPress pages.', 'art-of-touch'); ?></p>

        <?php if ($status === 'success') : ?>
            <div class="notice notice-success"><p><?php echo esc_html(sprintf(__('Import completed. %d pages processed.', 'art-of-touch'), $count)); ?></p></div>
        <?php elseif ($status === 'error') : ?>
            <div class="notice notice-error"><p><?php esc_html_e('Import failed. Check PHP error log for details.', 'art-of-touch'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('aot_import_seed_action'); ?>
            <input type="hidden" name="action" value="aot_import_seed">
            <?php submit_button(__('Run Import Now', 'art-of-touch')); ?>
        </form>
    </div>
    <?php
}

function aot_handle_import_request(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(__('Forbidden', 'art-of-touch'));
    }

    check_admin_referer('aot_import_seed_action');

    try {
        $count = aot_import_seed_pages();
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'aot-import',
                    'aot_import' => 'success',
                    'count' => $count,
                ],
                admin_url('tools.php')
            )
        );
    } catch (Throwable $e) {
        error_log('AOT import failed: ' . $e->getMessage());
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'aot-import',
                    'aot_import' => 'error',
                ],
                admin_url('tools.php')
            )
        );
    }

    exit;
}
add_action('admin_post_aot_import_seed', 'aot_handle_import_request');

function aot_import_seed_pages(): int
{
    $seed_dir = get_template_directory() . '/seed';
    $files = glob($seed_dir . '/*.html');

    if ($files === false || empty($files)) {
        throw new RuntimeException('No seed html files found in ' . $seed_dir);
    }

    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    $slug_map = [];
    $front_page_id = 0;
    $processed = 0;

    foreach ($files as $file) {
        $filename = basename($file);
        $basename = strtolower(pathinfo($file, PATHINFO_FILENAME));
        $html = file_get_contents($file);

        if ($html === false) {
            continue;
        }

        $title = aot_extract_title_from_html($html, $basename);
        $content = aot_extract_content_from_html($html);

        $slug = aot_slug_for_source($basename);
        $page_id = aot_upsert_page_from_seed($filename, $title, $slug, $content);

        if ($page_id <= 0) {
            continue;
        }

        $slug_map[$basename] = $slug;
        if ($basename === 'index') {
            $front_page_id = $page_id;
        }

        $processed++;
    }

    update_option('aot_slug_map', $slug_map, false);

    if ($front_page_id > 0) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $front_page_id);
    }

    aot_assign_primary_menu();

    return $processed;
}

function aot_extract_title_from_html(string $html, string $fallback): string
{
    if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
        $title = trim(wp_strip_all_tags(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($title !== '') {
            return $title;
        }
    }

    return ucwords(str_replace(['-', '_'], ' ', $fallback));
}

function aot_extract_content_from_html(string $html): string
{
    if (preg_match('/<div id="wrapper">.*?<\/div>\s*<!-- \/WRAPPER -->/is', $html, $matches)) {
        return trim($matches[0]);
    }

    if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
        return trim($matches[1]);
    }

    return trim($html);
}

function aot_slug_for_source(string $basename): string
{
    if ($basename === 'index') {
        return 'home';
    }

    return sanitize_title($basename);
}

function aot_upsert_page_from_seed(string $source_file, string $title, string $slug, string $content): int
{
    $existing = get_posts([
        'post_type' => 'page',
        'meta_key' => '_aot_source_file',
        'meta_value' => $source_file,
        'post_status' => 'any',
        'numberposts' => 1,
        'fields' => 'ids',
    ]);

    $postarr = [
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => $slug,
        'post_content' => $content,
    ];

    if (!empty($existing)) {
        $postarr['ID'] = (int) $existing[0];
        $page_id = wp_update_post($postarr, true);
    } else {
        $page_id = wp_insert_post($postarr, true);
    }

    if (is_wp_error($page_id)) {
        return 0;
    }

    update_post_meta((int) $page_id, '_aot_source_file', $source_file);
    update_post_meta((int) $page_id, '_aot_imported_at', gmdate('c'));

    return (int) $page_id;
}

function aot_assign_primary_menu(): void
{
    $menu_name = 'Art of Touch Primary';
    $menu_obj = wp_get_nav_menu_object($menu_name);
    $menu_id = $menu_obj ? (int) $menu_obj->term_id : (int) wp_create_nav_menu($menu_name);

    if ($menu_id <= 0) {
        return;
    }

    $locations = get_theme_mod('nav_menu_locations', []);
    $locations['primary'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);

    $existing_items = wp_get_nav_menu_items($menu_id);
    if (is_array($existing_items)) {
        foreach ($existing_items as $item) {
            wp_delete_post((int) $item->ID, true);
        }
    }

    $home_url = home_url('/');
    wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-title' => 'Home',
        'menu-item-url' => $home_url,
        'menu-item-status' => 'publish',
        'menu-item-type' => 'custom',
    ]);

    $pages = get_pages([
        'sort_column' => 'menu_order,post_title',
        'post_status' => 'publish',
    ]);

    foreach ($pages as $page) {
        if ($page->post_name === 'home') {
            continue;
        }

        wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title' => $page->post_title,
            'menu-item-object' => 'page',
            'menu-item-object-id' => $page->ID,
            'menu-item-type' => 'post_type',
            'menu-item-status' => 'publish',
        ]);
    }

    wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-title' => 'Bücher',
        'menu-item-url' => 'https://books.crowdware.at/index_de.html',
        'menu-item-status' => 'publish',
        'menu-item-type' => 'custom',
    ]);

    wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-title' => 'Online Kurse',
        'menu-item-url' => 'https://artoftouch.info',
        'menu-item-status' => 'publish',
        'menu-item-type' => 'custom',
    ]);
}
