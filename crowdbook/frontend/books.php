<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Frontend_Books
{
    private CrowdBook_Books $books;
    private CrowdBook_Chapters $chapters;
    private CrowdBook_SML_Renderer $renderer;

    public function __construct(CrowdBook_Books $books, CrowdBook_Chapters $chapters, CrowdBook_SML_Renderer $renderer)
    {
        $this->books = $books;
        $this->chapters = $chapters;
        $this->renderer = $renderer;
    }

    public function render(): string
    {
        $books = $this->books->get_active();

        ob_start();
        echo '<section class="crowdbook-index">';
        echo '<header><h2>' . esc_html__('Alle Bücher', 'crowdbook') . '</h2></header>';

        if ($books === []) {
            echo '<p>' . esc_html__('Noch keine Bücher vorhanden.', 'crowdbook') . '</p>';
            echo '</section>';
            return (string) ob_get_clean();
        }

        echo '<div class="crowdbook-cards">';
        foreach ($books as $book) {
            $book_id = sanitize_key((string) $book->book_id);
            $title = (string) $book->title;
            $description = trim((string) $book->description);
            $cover_url = esc_url((string) ($book->cover_image_url ?? ''));
            $published_count = count($this->chapters->get_published($book_id));
            $url = home_url('/book/' . rawurlencode($book_id));
            $tagline = $description !== '' ? wp_trim_words(wp_strip_all_tags($description), 18, '...') : __('Noch keine Beschreibung hinterlegt.', 'crowdbook');
            $title_attr = esc_attr($title);
            $fallback_cover_url = plugins_url('../assets/default-book-cover.svg', __FILE__);

            echo '<article class="crowdbook-card">';
            echo '<a class="crowdbook-book-link" href="' . esc_url($url) . '" aria-label="' . $title_attr . '">';
            if ($cover_url !== '') {
                echo '<img class="crowdbook-book-cover" src="' . $cover_url . '" alt="' . $title_attr . '" loading="lazy" />';
            } else {
                echo '<img class="crowdbook-book-cover crowdbook-book-cover-default" src="' . esc_url($fallback_cover_url) . '" alt="' . $title_attr . '" loading="lazy" />';
            }
            echo '</a>';
            echo '<h3><a href="' . esc_url($url) . '">' . esc_html($title) . '</a></h3>';
            echo '<p class="crowdbook-book-tagline">' . esc_html($tagline) . '</p>';
            echo '<p class="crowdbook-card-meta">' . sprintf(esc_html__('%d veröffentlichte Kapitel', 'crowdbook'), $published_count) . '</p>';
            echo '</article>';
        }
        echo '</div>';
        echo '</section>';

        return (string) ob_get_clean();
    }
}
