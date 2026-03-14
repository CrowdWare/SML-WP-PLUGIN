<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Frontend_Editor
{
    private CrowdBook_Users $users;
    private CrowdBook_Books $books;
    private CrowdBook_Chapters $chapters;
    private CrowdBook_Frontend_Login $login;

    public function __construct(
        CrowdBook_Users $users,
        CrowdBook_Books $books,
        CrowdBook_Chapters $chapters,
        CrowdBook_Frontend_Login $login
    )
    {
        $this->users = $users;
        $this->books = $books;
        $this->chapters = $chapters;
        $this->login = $login;
    }

    public function render(): string
    {
        $user = $this->users->get_current_user();
        if (!$user) {
            $out = '<div class="crowdbook-editor">';
            $out .= '<p>' . esc_html__('Bitte logge dich ein, um ein Kapitel zu schreiben.', 'crowdbook') . '</p>';
            $out .= $this->login->render();
            $out .= '<p><a href="' . esc_url(home_url('/register')) . '">' . esc_html__('Noch kein Account? Jetzt registrieren', 'crowdbook') . '</a></p>';
            $out .= '</div>';
            return $out;
        }

        $chapter_id = isset($_GET['chapter_id']) ? (int) $_GET['chapter_id'] : 0;
        $chapter = $chapter_id > 0 ? $this->chapters->get_by_id($chapter_id) : null;
        if ($chapter && (int) $chapter->author_id !== (int) $user->id) {
            $chapter = null;
        }

        $title = $chapter ? (string) $chapter->title : '';
        $path_label = $chapter ? (string) $chapter->path_label : '';
        $markdown = $chapter ? (string) $chapter->markdown_content : '';
        $selected_book_id = $chapter ? sanitize_key((string) $chapter->book_id) : '';

        if ($chapter && (string) $chapter->status === 'published') {
            $pending_status = (string) ($chapter->pending_status ?? 'none');
            $pending_markdown = (string) ($chapter->pending_markdown_content ?? '');
            if (in_array($pending_status, ['draft', 'pending', 'rejected'], true) && trim($pending_markdown) !== '') {
                $title = (string) ($chapter->pending_title ?: $chapter->title);
                $path_label = $chapter->pending_path_label !== null ? (string) $chapter->pending_path_label : (string) $chapter->path_label;
                $markdown = $pending_markdown;
            }
        }

        $available_books = array_values(array_filter(
            $this->books->get_active(),
            fn($book) => is_object($book) && $this->books->can_user_extend_book($book, (int) $user->id)
        ));

        if (!$chapter && $selected_book_id === '' && $available_books !== []) {
            $selected_book_id = sanitize_key((string) $available_books[0]->book_id);
        }

        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crowdbook_editor_submit'])) {
            if (!isset($_POST['crowdbook_editor_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['crowdbook_editor_nonce'])), 'crowdbook_editor')) {
                $message = '<div class="crowdbook-notice error">' . esc_html__('Ungültige Anfrage.', 'crowdbook') . '</div>';
            } else {
                $posted_title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
                $posted_path = sanitize_text_field(wp_unslash($_POST['path_label'] ?? ''));
                $posted_markdown = (string) wp_unslash($_POST['markdown_content'] ?? '');
                $posted_book_id = sanitize_key((string) wp_unslash($_POST['book_id'] ?? ''));
                $posted_id = (int) ($_POST['chapter_id'] ?? 0);
                $action = sanitize_key((string) ($_POST['editor_action'] ?? 'draft'));

                $save = $this->chapters->create_or_update_draft((int) $user->id, $posted_title, $posted_path, $posted_markdown, $posted_book_id, $posted_id ?: null);

                if (!$save['ok']) {
                    $message = '<div class="crowdbook-notice error">' . esc_html($save['message']) . '</div>';
                } else {
                    $saved_id = (int) ($save['chapter_id'] ?? 0);
                    if ($action === 'submit' && $saved_id > 0) {
                        $submit = $this->chapters->submit($saved_id, (int) $user->id);
                        $message = '<div class="crowdbook-notice ' . esc_attr($submit['ok'] ? 'success' : 'error') . '">' . esc_html($submit['message']) . '</div>';
                    } else {
                        $message = '<div class="crowdbook-notice success">' . esc_html($save['message']) . '</div>';
                    }

                    $chapter = $saved_id > 0 ? $this->chapters->get_by_id($saved_id) : null;
                    $title = $chapter ? (string) $chapter->title : $posted_title;
                    $path_label = $chapter ? (string) $chapter->path_label : $posted_path;
                    $markdown = $chapter ? (string) $chapter->markdown_content : $posted_markdown;
                    if ($chapter && (string) $chapter->status === 'published') {
                        $pending_status = (string) ($chapter->pending_status ?? 'none');
                        $pending_markdown = (string) ($chapter->pending_markdown_content ?? '');
                        if (in_array($pending_status, ['draft', 'pending', 'rejected'], true) && trim($pending_markdown) !== '') {
                            $title = (string) ($chapter->pending_title ?: $chapter->title);
                            $path_label = $chapter->pending_path_label !== null ? (string) $chapter->pending_path_label : (string) $chapter->path_label;
                            $markdown = $pending_markdown;
                        }
                    }
                    $selected_book_id = $chapter ? sanitize_key((string) $chapter->book_id) : $posted_book_id;
                    $chapter_id = $saved_id;
                }
            }
        }

        ob_start();
        echo '<div class="crowdbook-editor">';
        echo wp_kses_post($message);
        if ($available_books === []) {
            echo '<div class="crowdbook-notice error">' . esc_html__('Es gibt noch kein Buch. Bitte zuerst im Dashboard ein Buch anlegen.', 'crowdbook') . '</div>';
            echo '<p><a class="button" href="' . esc_url(home_url('/dashboard')) . '">' . esc_html__('Zum Dashboard', 'crowdbook') . '</a></p>';
            echo '</div>';
            return (string) ob_get_clean();
        }

        echo '<form method="post">';
        wp_nonce_field('crowdbook_editor', 'crowdbook_editor_nonce');
        echo '<input type="hidden" name="chapter_id" value="' . (int) $chapter_id . '" />';
        echo '<p><label>' . esc_html__('Buch', 'crowdbook') . '</label><select name="book_id">';
        foreach ($available_books as $book) {
            $book_id = sanitize_key((string) $book->book_id);
            $title_text = (string) $book->title;
            echo '<option value="' . esc_attr($book_id) . '"' . selected($selected_book_id, $book_id, false) . '>' . esc_html($title_text) . '</option>';
        }
        echo '</select></p>';
        echo '<p><label>' . esc_html__('Title', 'crowdbook') . '</label><input type="text" name="title" value="' . esc_attr($title) . '" required /></p>';
        echo '<p><label>' . esc_html__('Zweig / Pfad (optional)', 'crowdbook') . '</label><input type="text" name="path_label" value="' . esc_attr($path_label) . '" placeholder="' . esc_attr__('z. B. Ubuntu-Weg', 'crowdbook') . '" /></p>';
        echo '<p><label>' . esc_html__('Markdown Content', 'crowdbook') . '</label></p>';
        echo '<div class="crowdbook-upload-tools">';
        echo '<input type="file" id="crowdbook_image_upload" accept="image/*" />';
        echo '<button type="button" id="crowdbook_upload_button" class="button">' . esc_html__('Bild hochladen & einfuegen', 'crowdbook') . '</button>';
        echo '<span id="crowdbook_upload_status" class="crowdbook-upload-status" aria-live="polite"></span>';
        echo '</div>';
        echo '<div id="crowdbook_monaco_editor" class="crowdbook-monaco"></div>';
        echo '<textarea id="crowdbook_markdown_content" name="markdown_content" rows="14" required>' . esc_textarea($markdown) . '</textarea>';
        echo '<p class="crowdbook-editor-actions">';
        echo '<button type="submit" name="editor_action" value="draft" class="button">' . esc_html__('Entwurf speichern', 'crowdbook') . '</button> ';
        echo '<button type="submit" name="editor_action" value="submit" class="button button-primary">' . esc_html__('Einreichen', 'crowdbook') . '</button>';
        echo '<input type="hidden" name="crowdbook_editor_submit" value="1" />';
        echo '</p>';
        echo '</form>';
        echo '</div>';

        return (string) ob_get_clean();
    }
}
