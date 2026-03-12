<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Frontend_Book_Index
{
    private CrowdBook_Chapters $chapters;
    private CrowdBook_Users $users;
    private CrowdBook_Books $books;
    private CrowdBook_SML_Renderer $renderer;

    public function __construct(
        CrowdBook_Chapters $chapters,
        CrowdBook_Users $users,
        CrowdBook_Books $books,
        CrowdBook_SML_Renderer $renderer
    )
    {
        $this->chapters = $chapters;
        $this->users = $users;
        $this->books = $books;
        $this->renderer = $renderer;
    }

    public function render(array $atts): string
    {
        $atts = shortcode_atts(['book' => ''], $atts, 'crowdbook_index');
        $book_id = sanitize_key((string) $atts['book']);
        if ($book_id === '') {
            $active = $this->books->get_active();
            if ($active === []) {
                return '<p>' . esc_html__('Es gibt noch keine Bücher.', 'crowdbook') . '</p>';
            }
            $book_id = sanitize_key((string) $active[0]->book_id);
        }

        $book = $this->books->get_by_book_id($book_id);
        $admin_preview = isset($_GET['preview']) && $_GET['preview'] === '1' && current_user_can('moderate_crowdbook');
        if ($book && $admin_preview && $this->books->has_pending_version($book)) {
            $book = $this->books->get_effective_preview($book);
        }
        if ($book) {
            $book_id = sanitize_key((string) $book->book_id);
        }
        $chapters = $this->chapters->get_published($book_id);
        usort($chapters, static function ($a, $b): int {
            $a_time = strtotime((string) ($a->published_at ?: $a->created_at));
            $b_time = strtotime((string) ($b->published_at ?: $b->created_at));
            return $a_time <=> $b_time;
        });
        $book_title = $book ? (string) $book->title : __('Buch', 'crowdbook');
        $prologue = $book ? (string) $book->prologue_markdown : '';
        $grouped = [];
        foreach ($chapters as $chapter) {
            $branch = trim((string) ($chapter->path_label ?? ''));
            if ($branch === '') {
                $branch = __('Hauptzweig', 'crowdbook');
            }
            $branch_key = sanitize_title($branch);
            if ($branch_key === '') {
                $branch_key = 'hauptzweig';
            }
            if (!isset($grouped[$branch_key])) {
                $grouped[$branch_key] = [
                    'label' => $branch,
                    'chapters' => [],
                ];
            }
            $grouped[$branch_key]['chapters'][] = $chapter;
        }

        $selected_branch = isset($_GET['branch']) ? sanitize_title((string) wp_unslash($_GET['branch'])) : '';
        $step = isset($_GET['step']) ? max(1, (int) $_GET['step']) : 1;

        ob_start();
        echo '<section class="crowdbook-index">';

        if ($selected_branch === '' || !isset($grouped[$selected_branch])) {
            if ($prologue !== '') {
                echo '<article class="crowdbook-prologue">';
                echo '<div class="crowdbook-markdown">' . $this->renderer->render_markdown_html($prologue) . '</div>';
                echo '</article>';
            }

            if ($grouped === []) {
                echo '<p>' . esc_html__('Noch keine veröffentlichten Kapitel in diesem Buch.', 'crowdbook') . '</p>';
            } else {
                echo '<div class="crowdbook-cards">';
                foreach ($grouped as $branch_key => $branch_data) {
                    $branch_label = (string) $branch_data['label'];
                    $branch_chapters = (array) $branch_data['chapters'];
                    $first = $branch_chapters[0] ?? null;
                    $author = $first ? $this->chapters->get_author_name((int) $first->author_id) : '';
                    $first_title = $first ? (string) $first->title : '';
                    $branch_url = add_query_arg(
                        [
                            'branch' => $branch_key,
                            'step' => 1,
                        ],
                        home_url('/book/' . rawurlencode($book_id))
                    );
                    echo '<article class="crowdbook-card crowdbook-branch-card">';
                    echo '<a class="crowdbook-branch-link" href="' . esc_url($branch_url) . '">';
                    echo '<h4>' . esc_html($branch_label) . '</h4>';
                    if ($first_title !== '') {
                        echo '<p class="crowdbook-branch-first-title">' . esc_html($first_title) . '</p>';
                    }
                    if ($author !== '') {
                        echo '<p class="crowdbook-card-meta">' . esc_html($author) . '</p>';
                    }
                    echo '<p class="crowdbook-card-meta">' . sprintf(esc_html__('%d Kapitel', 'crowdbook'), count($branch_chapters)) . '</p>';
                    echo '</a>';
                    echo '</article>';
                }
                echo '</div>';
            }
        } else {
            $branch_data = $grouped[$selected_branch];
            $branch_label = (string) $branch_data['label'];
            $branch_chapters = (array) $branch_data['chapters'];
            $total = count($branch_chapters);
            $index = max(1, min($step, $total));
            $current = $branch_chapters[$index - 1];
            $author = $this->chapters->get_author_name((int) $current->author_id);

            echo '<article class="crowdbook-chapter">';
            echo '<header class="crowdbook-chapter-head">';
            echo '<h3>' . esc_html($branch_label) . '</h3>';
            echo '<h4>' . esc_html((string) $current->title) . '</h4>';
            echo '<p class="crowdbook-meta">' . esc_html($author) . ' · ' . sprintf(esc_html__('Kapitel %1$d von %2$d', 'crowdbook'), $index, $total) . '</p>';
            echo '</header>';
            echo '<div class="crowdbook-markdown">' . $this->renderer->render_markdown_html((string) $current->markdown_content) . '</div>';
            echo '</article>';

            $base_book_url = home_url('/book/' . rawurlencode($book_id));
            $back_url = $base_book_url;
            echo '<nav class="crowdbook-reader-nav">';
            echo '<div class="crowdbook-reader-nav-main">';

            if ($index > 1) {
                $prev_url = add_query_arg(['branch' => $selected_branch, 'step' => $index - 1], $base_book_url);
                echo '<a class="btn btn-default crowdbook-nav-btn" href="' . esc_url($prev_url) . '">' . esc_html__('← Kapitel zurück', 'crowdbook') . '</a>';
            }
            if ($index < $total) {
                $next_url = add_query_arg(['branch' => $selected_branch, 'step' => $index + 1], $base_book_url);
                echo '<a class="btn btn-primary crowdbook-nav-btn" href="' . esc_url($next_url) . '">' . esc_html__('Nächstes Kapitel →', 'crowdbook') . '</a>';
            }
            echo '</div>';
            echo '<div class="crowdbook-reader-nav-back">';
            echo '<a class="btn btn-default crowdbook-nav-btn" href="' . esc_url($back_url) . '">' . esc_html__('↑ Zur Zweigübersicht', 'crowdbook') . '</a>';
            echo '</div>';
            echo '</nav>';
        }

        $logged_in = $this->users->get_current_user();
        $cta_url = $logged_in ? home_url('/editor') : home_url('/login');
        echo '<footer class="crowdbook-index-footer"><a class="btn btn-primary crowdbook-nav-btn" href="' . esc_url($cta_url) . '">' . esc_html__('Schreib deinen eigenen Weg', 'crowdbook') . '</a></footer>';

        echo '</section>';

        return (string) ob_get_clean();
    }

}
