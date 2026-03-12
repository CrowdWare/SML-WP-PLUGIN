<?php
get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();
        echo aot_render_page_content(get_the_ID());
    }
} else {
    echo '<div class="container"><p>No content found.</p></div>';
}

get_footer();
