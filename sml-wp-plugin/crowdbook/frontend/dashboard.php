<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Frontend_Dashboard
{
    private CrowdBook_Users $users;
    private CrowdBook_Chapters $chapters;
    private CrowdBook_Books $books;
    private CrowdBook_Reputation $reputation;

    public function __construct(CrowdBook_Users $users, CrowdBook_Chapters $chapters, CrowdBook_Books $books, CrowdBook_Reputation $reputation)
    {
        $this->users = $users;
        $this->chapters = $chapters;
        $this->books = $books;
        $this->reputation = $reputation;
    }

    public function render(): string
    {
        $user = $this->users->get_current_user();
        if (!$user) {
            return '<p>' . esc_html__('Bitte logge dich ein, um dein Dashboard zu sehen.', 'crowdbook') . '</p>';
        }

        $rows = $this->chapters->get_for_author((int) $user->id);
        $books = $this->books->get_all();
        $book_notice = '';
        $edit_book_id = isset($_GET['edit_book_id']) ? sanitize_key((string) $_GET['edit_book_id']) : '';
        $book_to_edit = $edit_book_id !== '' ? $this->books->get_by_book_id($edit_book_id) : null;
        $form_book_id = $book_to_edit ? sanitize_key((string) $book_to_edit->book_id) : '';
        $form_title = $book_to_edit ? (string) $book_to_edit->title : '';
        $form_description = $book_to_edit ? (string) $book_to_edit->description : '';
        $form_prologue = $book_to_edit ? (string) $book_to_edit->prologue_markdown : '';
        $form_cover = $book_to_edit ? (string) ($book_to_edit->cover_image_url ?? '') : '';
        $form_is_extendable = $book_to_edit ? ((int) ($book_to_edit->is_extendable ?? 1) === 1) : true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crowdbook_dashboard_create_book'])) {
            if (!isset($_POST['crowdbook_dashboard_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['crowdbook_dashboard_nonce'])), 'crowdbook_dashboard')) {
                $book_notice = '<div class="crowdbook-notice error">' . esc_html__('Ungültige Anfrage.', 'crowdbook') . '</div>';
            } else {
                $book_id = sanitize_key((string) wp_unslash($_POST['book_id'] ?? ''));
                $title = sanitize_text_field((string) wp_unslash($_POST['book_title'] ?? ''));
                $description = sanitize_textarea_field((string) wp_unslash($_POST['book_description'] ?? ''));
                $prologue = (string) wp_unslash($_POST['book_prologue_markdown'] ?? '');
                $cover = esc_url_raw((string) wp_unslash($_POST['book_cover_image_url'] ?? ''));
                $is_extendable = isset($_POST['book_is_extendable']) && (string) $_POST['book_is_extendable'] === '1';
                $is_edit = isset($_POST['crowdbook_dashboard_update_book']) && $_POST['crowdbook_dashboard_update_book'] === '1';
                $result = $is_edit
                    ? $this->books->update_book($book_id, $title, $description, $prologue, $cover, $is_extendable, (int) $user->id)
                    : $this->books->create_book($book_id, $title, $description, $prologue, $cover, 'pending', (int) $user->id, $is_extendable);
                $book_notice = '<div class="crowdbook-notice ' . esc_attr($result['ok'] ? 'success' : 'error') . '">' . esc_html((string) $result['message']) . '</div>';
                $books = $this->books->get_all();
                if ($result['ok']) {
                    $edit_book_id = '';
                    $book_to_edit = null;
                    $form_book_id = '';
                    $form_title = '';
                    $form_description = '';
                    $form_prologue = '';
                    $form_cover = '';
                    $form_is_extendable = true;
                }
            }
        }

        ob_start();
        echo '<div class="crowdbook-dashboard">';
        echo '<h3>' . esc_html__('Dein Dashboard', 'crowdbook') . '</h3>';
        echo '<p>' . sprintf(esc_html__('Eingeloggt als %s', 'crowdbook'), esc_html((string) $user->display_name)) . '</p>';
        echo '<p><a class="button" href="' . esc_url(home_url('/editor')) . '">' . esc_html__('Neues Kapitel schreiben', 'crowdbook') . '</a> ';
        echo '<a class="button" href="' . esc_url(add_query_arg('crowdbook_logout', '1', home_url('/'))) . '">' . esc_html__('Logout', 'crowdbook') . '</a></p>';

        $rep = $this->reputation->progress((int) $user->id);
        if ($rep['trusted']) {
            echo '<div class="crowdbook-reputation-trusted">';
            echo '<strong>' . esc_html__('Vertrauenswürdiger Autor', 'crowdbook') . '</strong> — ';
            echo esc_html__('Deine Kapitel werden direkt veröffentlicht. Die Community hat dir ihr Vertrauen gegeben.', 'crowdbook');
            echo '</div>';
        } else {
            $chapters_ok = $rep['published_chapters'] >= $rep['min_chapters'];
            $likes_ok    = $rep['total_likes'] >= $rep['min_likes'];
            echo '<div class="crowdbook-reputation-progress">';
            echo '<p><strong>' . esc_html__('Dein Weg zur direkten Veröffentlichung', 'crowdbook') . '</strong></p>';
            echo '<ul>';
            echo '<li class="' . ($chapters_ok ? 'rep-done' : 'rep-open') . '">';
            echo ($chapters_ok ? '✓ ' : '○ ') . sprintf(
                esc_html__('%1$d / %2$d veröffentlichte Kapitel', 'crowdbook'),
                $rep['published_chapters'], $rep['min_chapters']
            );
            echo '</li>';
            echo '<li class="' . ($likes_ok ? 'rep-done' : 'rep-open') . '">';
            echo ($likes_ok ? '✓ ' : '○ ') . sprintf(
                esc_html__('%1$d / %2$d Likes gesamt', 'crowdbook'),
                $rep['total_likes'], $rep['min_likes']
            );
            echo '</li>';
            echo '</ul>';
            echo '</div>';
        }

        if ($rows === []) {
            echo '<p>' . esc_html__('Noch keine Kapitel vorhanden.', 'crowdbook') . '</p>';
        } else {
            echo '<table class="crowdbook-table"><thead><tr>';
            echo '<th>' . esc_html__('Titel', 'crowdbook') . '</th>';
            echo '<th>' . esc_html__('Status', 'crowdbook') . '</th>';
            echo '<th>' . esc_html__('Likes', 'crowdbook') . '</th>';
            echo '<th>' . esc_html__('Aktion', 'crowdbook') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($rows as $row) {
                $edit_url = add_query_arg('chapter_id', (int) $row->id, home_url('/editor'));
                $status_text = (string) $row->status;
                $is_pending_rejected = (string) $row->status === 'published'
                    && in_array((string) ($row->pending_status ?? 'none'), ['draft', 'pending', 'rejected'], true);
                if ($is_pending_rejected) {
                    $status_text .= ' (' . (string) $row->pending_status . '-update)';
                }
                $feedback = trim((string) ($row->rejection_feedback ?? ''));
                $show_feedback = $feedback !== '' && (
                    (string) $row->status === 'rejected'
                    || ((string) ($row->pending_status ?? '') === 'rejected')
                );
                echo '<tr>';
                echo '<td>' . esc_html((string) $row->title) . '</td>';
                echo '<td>' . esc_html($status_text) . '</td>';
                echo '<td>' . (int) $row->like_count . '</td>';
                echo '<td><a href="' . esc_url($edit_url) . '">' . esc_html__('Bearbeiten', 'crowdbook') . '</a></td>';
                echo '</tr>';
                if ($show_feedback) {
                    echo '<tr class="crowdbook-rejection-row">';
                    echo '<td colspan="4" class="crowdbook-rejection-feedback">';
                    echo '<strong>' . esc_html__('Feedback vom Moderationsteam:', 'crowdbook') . '</strong> ';
                    echo esc_html($feedback);
                    echo '</td>';
                    echo '</tr>';
                }
            }

            echo '</tbody></table>';
        }

        echo '<hr />';
        echo '<h4>' . esc_html($book_to_edit ? __('Buch bearbeiten', 'crowdbook') : __('Neues Buch anlegen', 'crowdbook')) . '</h4>';
        echo wp_kses_post($book_notice);
        echo '<form method="post" class="crowdbook-book-create">';
        wp_nonce_field('crowdbook_dashboard', 'crowdbook_dashboard_nonce');
        echo '<p class="crowdbook-form-row"><label for="crowdbook_book_id">' . esc_html__('Book ID', 'crowdbook') . '</label><input id="crowdbook_book_id" type="text" name="book_id" placeholder="mein-buch" value="' . esc_attr($form_book_id) . '"' . ($book_to_edit ? ' readonly' : '') . ' required /></p>';
        echo '<p class="crowdbook-form-row"><label for="crowdbook_book_title">' . esc_html__('Titel', 'crowdbook') . '</label><input id="crowdbook_book_title" type="text" name="book_title" value="' . esc_attr($form_title) . '" required /></p>';
        echo '<p class="crowdbook-form-row"><label for="crowdbook_book_description">' . esc_html__('Beschreibung', 'crowdbook') . '</label><textarea id="crowdbook_book_description" name="book_description" rows="3">' . esc_textarea($form_description) . '</textarea></p>';
        $recommended_width = 800;
        $recommended_height = 1200;
        echo '<div class="crowdbook-form-row"><label for="crowdbook_book_cover">' . esc_html__('Cover (Portrait)', 'crowdbook') . '</label><div class="crowdbook-cover-field">';
        echo '<div id="crowdbook_cover_dropzone" class="crowdbook-cover-dropzone" tabindex="0">';
        echo '<p class="crowdbook-cover-dropzone-title">' . esc_html__('Bild hierher ziehen oder auswaehlen', 'crowdbook') . '</p>';
        echo '<p class="crowdbook-cover-dropzone-hint">' . sprintf(esc_html__('Empfohlen: %1$d x %2$d px. Upload wird automatisch auf Portrait-Cover skaliert.', 'crowdbook'), $recommended_width, $recommended_height) . '</p>';
        echo '<p><button type="button" id="crowdbook_cover_pick_button" class="button">' . esc_html__('Cover hochladen', 'crowdbook') . '</button></p>';
        echo '<input type="file" id="crowdbook_cover_upload" accept="image/*" hidden />';
        echo '</div>';
        echo '<input id="crowdbook_book_cover" type="url" name="book_cover_image_url" value="' . esc_attr($form_cover) . '" placeholder="https://..." />';
        echo '<p class="crowdbook-cover-meta">' . esc_html__('Oder externe URL eintragen. Empfohlen ist lokaler Upload.', 'crowdbook') . '</p>';
        echo '<p id="crowdbook_cover_status" class="crowdbook-upload-status" aria-live="polite"></p>';
        $preview_style = $form_cover === '' ? ' style="display:none"' : '';
        echo '<div id="crowdbook_cover_preview_wrap" class="crowdbook-cover-preview-wrap"' . $preview_style . '>';
        echo '<img id="crowdbook_cover_preview" class="crowdbook-cover-preview" src="' . esc_url($form_cover) . '" alt="' . esc_attr__('Cover-Vorschau', 'crowdbook') . '" />';
        echo '</div>';
        echo '</div></div>';
        echo '<p class="crowdbook-form-row"><label for="crowdbook_book_prologue">' . esc_html__('Prolog (Markdown)', 'crowdbook') . '</label><textarea id="crowdbook_book_prologue" name="book_prologue_markdown" rows="8">' . esc_textarea($form_prologue) . '</textarea></p>';
        echo '<p class="crowdbook-form-row"><label for="crowdbook_book_is_extendable">' . esc_html__('Buch ist erweiterbar', 'crowdbook') . '</label>';
        echo '<input id="crowdbook_book_is_extendable" type="checkbox" name="book_is_extendable" value="1"' . checked($form_is_extendable, true, false) . ' /> ';
        echo '<span>' . esc_html__('Andere Autoren dürfen Kapitel hinzufügen', 'crowdbook') . '</span></p>';
        if ($book_to_edit) {
            echo '<input type="hidden" name="crowdbook_dashboard_update_book" value="1" />';
            echo '<p class="crowdbook-form-actions"><button type="submit" class="button button-primary" name="crowdbook_dashboard_create_book" value="1">' . esc_html__('Buch aktualisieren', 'crowdbook') . '</button> ';
            echo '<a class="button" href="' . esc_url(home_url('/dashboard')) . '">' . esc_html__('Abbrechen', 'crowdbook') . '</a></p>';
        } else {
            echo '<p class="crowdbook-form-actions"><button type="submit" class="button button-primary" name="crowdbook_dashboard_create_book" value="1">' . esc_html__('Buch erstellen', 'crowdbook') . '</button></p>';
        }
        echo '</form>';

        if ($books !== []) {
            echo '<h4>' . esc_html__('Bücher (mit Status)', 'crowdbook') . '</h4>';
            echo '<ul>';
            foreach ($books as $book) {
                $book_url = home_url('/book/' . rawurlencode((string) $book->book_id));
                $edit_url = add_query_arg('edit_book_id', sanitize_key((string) $book->book_id), home_url('/dashboard'));
                $status = (string) ($book->status ?? '');
                if ((string) $book->status === 'active' && $this->books->has_pending_version($book)) {
                    $status = 'updated (' . (string) ($book->pending_status ?? 'pending') . ')';
                }
                echo '<li>';
                if ($status === 'active') {
                    echo '<a href="' . esc_url($book_url) . '">' . esc_html((string) $book->title) . '</a>';
                } else {
                    echo esc_html((string) $book->title);
                }
                echo ' <code>(' . esc_html((string) $book->book_id) . ')</code> ';
                echo '<strong>[' . esc_html($status) . ']</strong>';
                $extendable_text = ((int) ($book->is_extendable ?? 1) === 1) ? __('offen', 'crowdbook') : __('geschlossen', 'crowdbook');
                echo ' <em>(' . esc_html($extendable_text) . ')</em>';
                echo ' · <a href="' . esc_url($edit_url) . '">' . esc_html__('Bearbeiten', 'crowdbook') . '</a>';
                echo '</li>';
            }
            echo '</ul>';
        }

        echo '</div>';

        return (string) ob_get_clean();
    }
}
