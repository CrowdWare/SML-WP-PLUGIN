<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Social
{
    private CrowdBook_Chapters $chapters;

    public function __construct(CrowdBook_Chapters $chapters)
    {
        $this->chapters = $chapters;
    }

    public function register(): void
    {
        add_action('wp_head', [$this, 'output_open_graph_tags']);
    }

    public function output_open_graph_tags(): void
    {
        $slug = get_query_var('crowdbook_chapter');
        if (!is_string($slug) || $slug === '') {
            return;
        }

        $chapter = $this->chapters->get_by_slug($slug);
        if (!$chapter || (string) $chapter->status !== 'published') {
            return;
        }

        $title = (string) $chapter->title;
        $description = wp_trim_words(wp_strip_all_tags((string) $chapter->markdown_content), 30, '...');
        $url = $this->chapters->chapter_url((string) $chapter->slug);
        $image = home_url('/images/crowdbook-share.jpg');

        echo '<meta property="og:title" content="' . esc_attr($title . ' – Choose Your Incarnation') . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        echo '<meta property="og:image" content="' . esc_url($image) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:site_name" content="Choose Your Incarnation – crowdware.info" />' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
        echo '<meta name="twitter:image" content="' . esc_url($image) . '" />' . "\n";
    }

    public function render_share_buttons(string $title, string $url): string
    {
        $enc_url = rawurlencode($url);
        $enc_title = rawurlencode($title);

        $out = '<div class="crowdbook-share">';
        $out .= '<a href="https://t.me/share/url?url=' . esc_attr($enc_url) . '&text=' . esc_attr($enc_title) . '" target="_blank" rel="noopener">Telegram</a>';
        $out .= '<a href="https://wa.me/?text=' . esc_attr($enc_title . '%20' . $enc_url) . '" target="_blank" rel="noopener">WhatsApp</a>';
        $out .= '<a href="https://facebook.com/sharer/sharer.php?u=' . esc_attr($enc_url) . '" target="_blank" rel="noopener">Facebook</a>';
        $out .= '<a href="https://x.com/intent/tweet?url=' . esc_attr($enc_url) . '&text=' . esc_attr($enc_title) . '" target="_blank" rel="noopener">X</a>';
        $out .= '<button type="button" class="crowdbook-copy-link" data-url="' . esc_attr($url) . '">' . esc_html__('Link kopieren', 'crowdbook') . '</button>';
        $out .= '</div>';

        return $out;
    }
}
