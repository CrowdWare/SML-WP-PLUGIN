<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Admin_Books_Page
{
    private CrowdBook_Books $books;
    /**
     * @var array<int, array{message: string, type: string}>
     */
    private array $notices = [];

    public function __construct(CrowdBook_Books $books)
    {
        $this->books = $books;
    }

    public function handle_actions(): void
    {
        if (!current_user_can('moderate_crowdbook')) {
            return;
        }

        $action = isset($_GET['cb_book_action']) ? sanitize_key((string) $_GET['cb_book_action']) : '';
        $book_id = isset($_GET['book_id']) ? sanitize_key((string) $_GET['book_id']) : '';
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field((string) $_GET['_wpnonce']) : '';

        if ($action === '' || $book_id === '') {
            return;
        }

        if (!wp_verify_nonce($nonce, 'crowdbook_admin_book_' . $book_id . '_' . $action)) {
            return;
        }

        if ($action === 'archive') {
            $this->books->update_status($book_id, 'archived');
            $this->add_notice(__('Buch wurde archiviert.', 'crowdbook'), 'success');
        } elseif ($action === 'activate') {
            $this->books->update_status($book_id, 'active');
            $this->add_notice(__('Buch wurde aktiviert.', 'crowdbook'), 'success');
        } elseif ($action === 'publish') {
            $this->books->update_status($book_id, 'active');
            $this->add_notice(__('Buch wurde veröffentlicht.', 'crowdbook'), 'success');
        } elseif ($action === 'pending') {
            $this->books->update_status($book_id, 'pending');
            $this->add_notice(__('Buch wurde auf Pending gesetzt.', 'crowdbook'), 'success');
        } elseif ($action === 'reject_update') {
            $this->books->reject_pending_version($book_id);
            $this->add_notice(__('Buch-Update wurde abgelehnt. Live-Version bleibt aktiv.', 'crowdbook'), 'success');
        } elseif ($action === 'delete') {
            $ok = $this->books->delete_book($book_id);
            if (!$ok) {
                $this->add_notice(__('Buch konnte nicht gelöscht werden (evtl. Standardbuch oder Kapitel vorhanden).', 'crowdbook'), 'error');
            } else {
                $this->add_notice(__('Buch wurde gelöscht.', 'crowdbook'), 'success');
            }
        }
    }

    public function render(): void
    {
        if (!current_user_can('moderate_crowdbook')) {
            wp_die(esc_html__('Keine Berechtigung.', 'crowdbook'));
        }

        $this->render_notices();
        $rows = $this->books->get_all();
        $preview_book_id = isset($_GET['preview_book']) ? sanitize_key((string) $_GET['preview_book']) : '';
        $preview_book = $preview_book_id !== '' ? $this->books->get_by_book_id($preview_book_id) : null;
        $preview_render = $preview_book ? $this->books->get_effective_preview($preview_book) : null;
        $preview_is_update = $preview_book && $this->books->has_pending_version($preview_book);

        echo '<div class="wrap"><h1>CrowdBooks – Bücher</h1>';

        if ($preview_book && $preview_render) {
            echo '<h2>Vorschau</h2>';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th>Book ID</th><td><code>' . esc_html((string) $preview_render->book_id) . '</code></td></tr>';
            echo '<tr><th>Titel</th><td>' . esc_html((string) $preview_render->title) . '</td></tr>';
            $preview_status = (string) $preview_book->status;
            if ($preview_is_update) {
                $preview_status .= ' + pending-update (' . (string) ($preview_book->pending_status ?? 'pending') . ')';
            }
            echo '<tr><th>Status</th><td><strong>' . esc_html($preview_status) . '</strong></td></tr>';
            echo '<tr><th>Beschreibung</th><td>' . esc_html((string) $preview_render->description) . '</td></tr>';
            echo '<tr><th>Cover</th><td>';
            if ((string) ($preview_render->cover_image_url ?? '') !== '') {
                echo '<img src="' . esc_url((string) $preview_render->cover_image_url) . '" alt="' . esc_attr((string) $preview_render->title) . '" style="max-width:220px;height:auto;border-radius:8px;border:1px solid #ccd0d4;" />';
            } else {
                echo '<em>Kein Cover</em>';
            }
            echo '</td></tr>';
            echo '<tr><th>Prolog (Markdown)</th><td><textarea class="large-text code" rows="10" readonly>' . esc_textarea((string) $preview_render->prologue_markdown) . '</textarea></td></tr>';
            echo '</tbody></table>';
            echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=crowdbook-books')) . '">Vorschau schließen</a></p>';
        }

        echo '<h2>Vorhandene Bücher</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Book ID</th><th>Titel</th><th>Status</th><th>Beschreibung</th><th>Aktionen</th></tr></thead><tbody>';

        foreach ($rows as $row) {
            $archive_url = wp_nonce_url(add_query_arg([
                'page' => 'crowdbook-books',
                'cb_book_action' => 'archive',
                'book_id' => (string) $row->book_id,
            ], admin_url('admin.php')), 'crowdbook_admin_book_' . (string) $row->book_id . '_archive');

            $activate_url = wp_nonce_url(add_query_arg([
                'page' => 'crowdbook-books',
                'cb_book_action' => 'activate',
                'book_id' => (string) $row->book_id,
            ], admin_url('admin.php')), 'crowdbook_admin_book_' . (string) $row->book_id . '_activate');

            $publish_url = wp_nonce_url(add_query_arg([
                'page' => 'crowdbook-books',
                'cb_book_action' => 'publish',
                'book_id' => (string) $row->book_id,
            ], admin_url('admin.php')), 'crowdbook_admin_book_' . (string) $row->book_id . '_publish');

            $pending_url = wp_nonce_url(add_query_arg([
                'page' => 'crowdbook-books',
                'cb_book_action' => 'pending',
                'book_id' => (string) $row->book_id,
            ], admin_url('admin.php')), 'crowdbook_admin_book_' . (string) $row->book_id . '_pending');

            $reject_update_url = wp_nonce_url(add_query_arg([
                'page' => 'crowdbook-books',
                'cb_book_action' => 'reject_update',
                'book_id' => (string) $row->book_id,
            ], admin_url('admin.php')), 'crowdbook_admin_book_' . (string) $row->book_id . '_reject_update');

            $preview_url = add_query_arg([
                'page' => 'crowdbook-books',
                'preview_book' => (string) $row->book_id,
            ], admin_url('admin.php'));

            $delete_url = wp_nonce_url(add_query_arg([
                'page' => 'crowdbook-books',
                'cb_book_action' => 'delete',
                'book_id' => (string) $row->book_id,
            ], admin_url('admin.php')), 'crowdbook_admin_book_' . (string) $row->book_id . '_delete');

            echo '<tr>';
            $has_pending_update = $this->books->has_pending_version($row);
            $display_title = $has_pending_update
                ? (string) (($row->pending_title ?? '') !== '' ? $row->pending_title : $row->title)
                : (string) $row->title;
            $display_desc = $has_pending_update
                ? (string) ($row->pending_description ?? $row->description)
                : (string) $row->description;
            $display_status = (string) $row->status;
            if ($has_pending_update) {
                $display_status = 'updated (' . (string) ($row->pending_status ?? 'pending') . ')';
            }
            echo '<td><code>' . esc_html((string) $row->book_id) . '</code></td>';
            echo '<td>' . esc_html($display_title) . '</td>';
            echo '<td>' . esc_html($display_status) . '</td>';
            echo '<td>' . esc_html($display_desc) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url($preview_url) . '">Vorschau</a> | ';
            if ((string) $row->status === 'pending' || $has_pending_update) {
                echo '<a href="' . esc_url($publish_url) . '">Publishen</a>';
                if ($has_pending_update) {
                    echo ' | <a href="' . esc_url($reject_update_url) . '">Update ablehnen</a>';
                }
            } elseif ((string) $row->status === 'active') {
                echo '<a href="' . esc_url($archive_url) . '">Archivieren</a>';
            } else {
                echo '<a href="' . esc_url($activate_url) . '">Aktivieren</a>';
            }
            if ((string) $row->status !== 'pending') {
                echo ' | <a href="' . esc_url($pending_url) . '">Auf Pending setzen</a>';
            }
            echo ' | <a href="' . esc_url($delete_url) . '">Löschen</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    private function add_notice(string $message, string $type): void
    {
        $type = in_array($type, ['success', 'error', 'warning'], true) ? $type : 'success';
        $this->notices[] = [
            'message' => $message,
            'type' => $type,
        ];
    }

    private function render_notices(): void
    {
        foreach ($this->notices as $notice) {
            $class = $notice['type'] === 'error' ? 'notice notice-error' : 'notice notice-success';
            echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($notice['message']) . '</p></div>';
        }
    }
}
