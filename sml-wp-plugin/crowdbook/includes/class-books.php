<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Books
{
    public function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'crowdbook_books';
    }

    public function get_by_book_id(string $book_id): ?object
    {
        global $wpdb;

        $book_id = sanitize_key($book_id);
        if ($book_id === '') {
            return null;
        }

        $table = $this->table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE book_id = %s LIMIT 1", $book_id));
        if (is_object($row)) {
            return $row;
        }

        // Tolerant lookup: allow URLs without dashes (e.g. spieldeslebens -> spiel-des-lebens).
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE REPLACE(book_id, '-', '') = %s LIMIT 1",
            str_replace('-', '', $book_id)
        ));

        return is_object($row) ? $row : null;
    }

    public function get_all(?string $status = null): array
    {
        global $wpdb;

        $table = $this->table_name();

        if ($status !== null && in_array($status, ['pending', 'active', 'archived'], true)) {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status = %s ORDER BY title ASC", $status));
            return is_array($rows) ? $rows : [];
        }

        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY title ASC");

        return is_array($rows) ? $rows : [];
    }

    public function get_active(): array
    {
        return $this->get_all('active');
    }

    public function create_book(
        string $book_id,
        string $title,
        string $description = '',
        string $prologue_markdown = '',
        string $cover_image_url = '',
        string $status = 'pending'
    ): array
    {
        global $wpdb;

        $book_id = sanitize_key($book_id);
        $title = sanitize_text_field($title);
        $description = sanitize_textarea_field($description);
        $prologue_markdown = wp_kses_post($prologue_markdown);
        $cover_image_url = esc_url_raw($cover_image_url);
        $status = sanitize_key($status);
        if (!in_array($status, ['pending', 'active', 'archived'], true)) {
            $status = 'pending';
        }

        if ($book_id === '' || $title === '') {
            return ['ok' => false, 'message' => __('Book ID und Titel sind erforderlich.', 'crowdbook')];
        }

        if ($this->get_by_book_id($book_id)) {
            return ['ok' => false, 'message' => __('Book ID existiert bereits.', 'crowdbook')];
        }

        $inserted = $wpdb->insert(
            $this->table_name(),
            [
                'book_id' => $book_id,
                'title' => $title,
                'description' => $description,
                'prologue_markdown' => $prologue_markdown,
                'cover_image_url' => $cover_image_url,
                'status' => $status,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$inserted) {
            return ['ok' => false, 'message' => __('Buch konnte nicht erstellt werden.', 'crowdbook')];
        }

        $message = $status === 'pending'
            ? __('Buch wurde eingereicht und wartet auf Freigabe.', 'crowdbook')
            : __('Buch wurde erstellt.', 'crowdbook');

        return ['ok' => true, 'message' => $message];
    }

    public function update_status(string $book_id, string $status): bool
    {
        global $wpdb;

        $book_id = sanitize_key($book_id);
        if ($book_id === '' || !in_array($status, ['pending', 'active', 'archived'], true)) {
            return false;
        }

        $existing = $this->get_by_book_id($book_id);
        if (!$existing) {
            return false;
        }

        if ($status === 'active' && (string) ($existing->status ?? '') === 'active' && $this->has_pending_version($existing)) {
            return $this->apply_pending_version($book_id);
        }

        $updated = $wpdb->update(
            $this->table_name(),
            [
                'status' => $status,
                'updated_at' => current_time('mysql'),
            ],
            ['book_id' => $book_id],
            ['%s', '%s'],
            ['%s']
        );

        return $updated !== false;
    }

    public function update_book(
        string $book_id,
        string $title,
        string $description = '',
        string $prologue_markdown = '',
        string $cover_image_url = ''
    ): array
    {
        global $wpdb;

        $book_id = sanitize_key($book_id);
        $title = sanitize_text_field($title);
        $description = sanitize_textarea_field($description);
        $prologue_markdown = wp_kses_post($prologue_markdown);
        $cover_image_url = esc_url_raw($cover_image_url);

        if ($book_id === '' || $title === '') {
            return ['ok' => false, 'message' => __('Book ID und Titel sind erforderlich.', 'crowdbook')];
        }

        $existing = $this->get_by_book_id($book_id);
        if (!$existing) {
            return ['ok' => false, 'message' => __('Buch nicht gefunden.', 'crowdbook')];
        }

        if ((string) ($existing->status ?? '') === 'active') {
            $pending_update = $wpdb->update(
                $this->table_name(),
                [
                    'pending_title' => $title,
                    'pending_description' => $description,
                    'pending_prologue_markdown' => $prologue_markdown,
                    'pending_cover_image_url' => $cover_image_url,
                    'pending_status' => 'pending',
                    'updated_at' => current_time('mysql'),
                ],
                ['book_id' => $book_id],
                ['%s', '%s', '%s', '%s', '%s', '%s'],
                ['%s']
            );

            if ($pending_update === false) {
                return ['ok' => false, 'message' => __('Buchänderung konnte nicht eingereicht werden.', 'crowdbook')];
            }

            return ['ok' => true, 'message' => __('Buchänderung wurde eingereicht und wartet auf Freigabe. Live-Version bleibt sichtbar.', 'crowdbook')];
        }

        $updated = $wpdb->update(
            $this->table_name(),
            [
                'title' => $title,
                'description' => $description,
                'prologue_markdown' => $prologue_markdown,
                'cover_image_url' => $cover_image_url,
                'pending_title' => null,
                'pending_description' => null,
                'pending_prologue_markdown' => null,
                'pending_cover_image_url' => null,
                'pending_status' => 'none',
                'updated_at' => current_time('mysql'),
            ],
            ['book_id' => $book_id],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%s']
        );

        if ($updated === false) {
            return ['ok' => false, 'message' => __('Buch konnte nicht aktualisiert werden.', 'crowdbook')];
        }

        return ['ok' => true, 'message' => __('Buch wurde aktualisiert.', 'crowdbook')];
    }

    public function delete_book(string $book_id): bool
    {
        global $wpdb;

        $book_id = sanitize_key($book_id);
        if ($book_id === '') {
            return false;
        }

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}crowdbook_chapters WHERE book_id = %s",
            $book_id
        ));

        if ((int) $count > 0) {
            return false;
        }

        $deleted = $wpdb->delete($this->table_name(), ['book_id' => $book_id], ['%s']);

        return $deleted !== false;
    }

    public function purge_legacy_seed_book(): void
    {
        global $wpdb;

        $book_id = 'choose-your-incarnation';
        $book = $this->get_by_book_id($book_id);
        if (!$book) {
            return;
        }

        $chapter_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}crowdbook_chapters WHERE book_id = %s",
            $book_id
        ));

        if ($chapter_count > 0) {
            return;
        }

        $wpdb->delete($this->table_name(), ['book_id' => $book_id], ['%s']);
    }

    public function has_pending_version(object $book): bool
    {
        $pending_status = (string) ($book->pending_status ?? 'none');
        if (!in_array($pending_status, ['pending', 'rejected', 'draft'], true)) {
            return false;
        }

        return trim((string) ($book->pending_title ?? '')) !== ''
            || trim((string) ($book->pending_description ?? '')) !== ''
            || trim((string) ($book->pending_prologue_markdown ?? '')) !== ''
            || trim((string) ($book->pending_cover_image_url ?? '')) !== '';
    }

    public function get_effective_preview(object $book): object
    {
        $preview = clone $book;
        if (!$this->has_pending_version($book)) {
            return $preview;
        }

        $preview->title = (string) ($book->pending_title ?: $book->title);
        $preview->description = (string) ($book->pending_description ?? $book->description);
        $preview->prologue_markdown = (string) ($book->pending_prologue_markdown ?? $book->prologue_markdown);
        $preview->cover_image_url = (string) ($book->pending_cover_image_url ?? $book->cover_image_url);

        return $preview;
    }

    public function reject_pending_version(string $book_id): bool
    {
        global $wpdb;

        $book_id = sanitize_key($book_id);
        if ($book_id === '') {
            return false;
        }

        $updated = $wpdb->update(
            $this->table_name(),
            [
                'pending_status' => 'rejected',
                'updated_at' => current_time('mysql'),
            ],
            ['book_id' => $book_id],
            ['%s', '%s'],
            ['%s']
        );

        return $updated !== false;
    }

    private function apply_pending_version(string $book_id): bool
    {
        global $wpdb;

        $book = $this->get_by_book_id($book_id);
        if (!$book || !$this->has_pending_version($book)) {
            return false;
        }

        $updated = $wpdb->update(
            $this->table_name(),
            [
                'title' => (string) ($book->pending_title ?: $book->title),
                'description' => (string) ($book->pending_description ?? $book->description),
                'prologue_markdown' => (string) ($book->pending_prologue_markdown ?? $book->prologue_markdown),
                'cover_image_url' => (string) ($book->pending_cover_image_url ?? $book->cover_image_url),
                'status' => 'active',
                'pending_title' => null,
                'pending_description' => null,
                'pending_prologue_markdown' => null,
                'pending_cover_image_url' => null,
                'pending_status' => 'none',
                'updated_at' => current_time('mysql'),
            ],
            ['book_id' => $book_id],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%s']
        );

        return $updated !== false;
    }
}
