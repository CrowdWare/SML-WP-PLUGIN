<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Admin_Chapters_Page
{
    private CrowdBook_Chapters $chapters;
    private CrowdBook_Books $books;

    public function __construct(CrowdBook_Chapters $chapters, CrowdBook_Books $books)
    {
        $this->chapters = $chapters;
        $this->books = $books;
    }

    public function handle_actions(): void
    {
        if (!current_user_can('moderate_crowdbook')) {
            return;
        }

        // Reject is POST-only to enforce mandatory feedback.
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['cb_action'])
            && (string) $_POST['cb_action'] === 'reject'
        ) {
            $chapter_id = isset($_POST['chapter_id']) ? (int) $_POST['chapter_id'] : 0;
            $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field((string) wp_unslash($_POST['_wpnonce'])) : '';
            if ($chapter_id > 0 && wp_verify_nonce($nonce, 'crowdbook_admin_chapter_' . $chapter_id . '_reject')) {
                $feedback = sanitize_textarea_field((string) wp_unslash($_POST['rejection_feedback'] ?? ''));
                $this->chapters->moderate_status($chapter_id, 'rejected', $feedback);
            }
            return;
        }

        $action = isset($_GET['cb_action']) ? sanitize_key((string) $_GET['cb_action']) : '';
        $chapter_id = isset($_GET['chapter_id']) ? (int) $_GET['chapter_id'] : 0;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field((string) $_GET['_wpnonce']) : '';

        if ($action === '' || $chapter_id <= 0) {
            return;
        }

        if (!wp_verify_nonce($nonce, 'crowdbook_admin_chapter_' . $chapter_id . '_' . $action)) {
            return;
        }

        if ($action === 'publish') {
            $this->chapters->moderate_status($chapter_id, 'published');
        } elseif ($action === 'draft') {
            $this->chapters->moderate_status($chapter_id, 'draft');
        } elseif ($action === 'delete') {
            $this->chapters->delete_chapter($chapter_id);
        }
    }

    public function render(): void
    {
        if (!current_user_can('moderate_crowdbook')) {
            wp_die(esc_html__('Keine Berechtigung.', 'crowdbook'));
        }

        $status_filter = isset($_GET['status']) ? sanitize_key((string) $_GET['status']) : '';
        $book_filter = isset($_GET['book']) ? sanitize_key((string) $_GET['book']) : '';
        $rows = $this->chapters->get_all(
            in_array($status_filter, ['draft', 'pending', 'updated', 'published', 'rejected'], true) ? $status_filter : null,
            $book_filter !== '' ? $book_filter : null
        );
        $books = $this->books->get_all();

        echo '<div class="wrap"><h1>CrowdBooks – Kapitel Moderation</h1>';
        echo '<p>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=crowdbook-chapters')) . '">Alle</a> | ';
        echo '<a href="' . esc_url(add_query_arg('status', 'draft', admin_url('admin.php?page=crowdbook-chapters'))) . '">Draft</a> | ';
        echo '<a href="' . esc_url(add_query_arg('status', 'pending', admin_url('admin.php?page=crowdbook-chapters'))) . '">Pending</a> | ';
        echo '<a href="' . esc_url(add_query_arg('status', 'updated', admin_url('admin.php?page=crowdbook-chapters'))) . '">Updated</a> | ';
        echo '<a href="' . esc_url(add_query_arg('status', 'published', admin_url('admin.php?page=crowdbook-chapters'))) . '">Published</a> | ';
        echo '<a href="' . esc_url(add_query_arg('status', 'rejected', admin_url('admin.php?page=crowdbook-chapters'))) . '">Rejected</a>';
        echo '</p>';
        echo '<p><strong>Buchfilter:</strong> ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=crowdbook-chapters')) . '">Alle Bücher</a>';
        foreach ($books as $book) {
            $url = add_query_arg(['book' => (string) $book->book_id], admin_url('admin.php?page=crowdbook-chapters'));
            echo ' | <a href="' . esc_url($url) . '">' . esc_html((string) $book->title) . '</a>';
        }
        echo '</p>';

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Buch</th><th>Titel</th><th>Autor</th><th>Path Label</th><th>Status</th><th>Spam Score</th><th>Likes</th><th>Datum</th><th>Aktionen</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $author = $this->chapters->get_author_name((int) $row->author_id);
            $book = $this->books->get_by_book_id((string) $row->book_id);
            $preview_url = add_query_arg('preview', '1', $this->chapters->chapter_url((string) $row->slug));

            $publish_url = wp_nonce_url(add_query_arg([
                'page' => 'crowdbook-chapters',
                'cb_action' => 'publish',
                'chapter_id' => (int) $row->id,
            ], admin_url('admin.php')), 'crowdbook_admin_chapter_' . (int) $row->id . '_publish');

            $reject_nonce = wp_create_nonce('crowdbook_admin_chapter_' . (int) $row->id . '_reject');

            $delete_url = wp_nonce_url(add_query_arg([
                'page' => 'crowdbook-chapters',
                'cb_action' => 'delete',
                'chapter_id' => (int) $row->id,
            ], admin_url('admin.php')), 'crowdbook_admin_chapter_' . (int) $row->id . '_delete');

            echo '<tr>';
            echo '<td>' . esc_html($book ? (string) $book->title : (string) $row->book_id) . '</td>';
            $has_update_draft = (string) $row->status === 'published'
                && in_array((string) ($row->pending_status ?? 'none'), ['draft', 'pending', 'rejected'], true)
                && trim((string) ($row->pending_markdown_content ?? '')) !== '';
            $display_title = $has_update_draft
                ? (string) (($row->pending_title ?? '') !== '' ? $row->pending_title : $row->title)
                : (string) $row->title;
            $display_path = $has_update_draft
                ? (string) ($row->pending_path_label !== null ? $row->pending_path_label : $row->path_label)
                : (string) $row->path_label;
            echo '<td>' . esc_html($display_title) . '</td>';
            echo '<td>' . esc_html($author) . '</td>';
            echo '<td>' . esc_html($display_path) . '</td>';
            $display_status = (string) $row->status;
            if ($has_update_draft) {
                $pending_state = (string) ($row->pending_status ?? 'draft');
                if ($pending_state === 'pending') {
                    $display_status = 'updated (wartet auf Moderation)';
                } elseif ($pending_state === 'draft') {
                    $display_status = 'updated (nur gespeichert)';
                } elseif ($pending_state === 'rejected') {
                    $display_status = 'updated (abgelehnt)';
                }
            }
            echo '<td>' . esc_html($display_status) . '</td>';
            $display_spam = $has_update_draft && (string) ($row->pending_status ?? '') === 'pending'
                ? (string) $row->pending_spam_score
                : (string) $row->spam_score;
            echo '<td>' . esc_html($display_spam) . '</td>';
            echo '<td>' . (int) $row->like_count . '</td>';
            echo '<td>' . esc_html((string) $row->created_at) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url($preview_url) . '" target="_blank" rel="noopener">Vorschau</a> | ';
            echo '<a href="' . esc_url($publish_url) . '">Publizieren</a> | ';
            echo '<details class="cb-reject-details" style="display:inline-block">';
            echo '<summary style="cursor:pointer;color:#b32d2e;">Ablehnen</summary>';
            echo '<div style="margin-top:6px;padding:8px;background:#fff3cd;border:1px solid #e6a817;border-radius:4px;">';
            echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=crowdbook-chapters')) . '">';
            echo '<input type="hidden" name="cb_action" value="reject">';
            echo '<input type="hidden" name="chapter_id" value="' . (int) $row->id . '">';
            echo '<input type="hidden" name="_wpnonce" value="' . esc_attr($reject_nonce) . '">';
            echo '<label style="display:block;margin-bottom:4px;font-weight:600;">Feedback für den Autor (Pflicht):</label>';
            echo '<textarea name="rejection_feedback" rows="3" style="width:100%;min-width:280px;" required placeholder="' . esc_attr__('Was soll der Autor überarbeiten?', 'crowdbook') . '"></textarea>';
            echo '<div style="margin-top:6px;"><button type="submit" class="button button-secondary" style="color:#b32d2e;">Ablehnen bestätigen</button></div>';
            echo '</form>';
            echo '</div>';
            echo '</details>';
            echo ' | ';
            echo '<a href="' . esc_url($delete_url) . '">Löschen</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }
}
