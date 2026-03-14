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
        string $status = 'pending',
        int $created_by_user_id = 0,
        bool $is_extendable = true
    ): array
    {
        global $wpdb;

        $book_id = sanitize_key($book_id);
        $title = sanitize_text_field($title);
        $description = sanitize_textarea_field($description);
        $prologue_markdown = wp_kses_post($prologue_markdown);
        $cover_image_url = esc_url_raw($cover_image_url);
        $status = sanitize_key($status);
        $created_by_user_id = max(0, (int) $created_by_user_id);
        $is_extendable_db = $is_extendable ? 1 : 0;
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
                'created_by_user_id' => $created_by_user_id,
                'is_extendable' => $is_extendable_db,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
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
        string $cover_image_url = '',
        ?bool $is_extendable = null,
        int $actor_user_id = 0
    ): array
    {
        global $wpdb;

        $book_id = sanitize_key($book_id);
        $title = sanitize_text_field($title);
        $description = sanitize_textarea_field($description);
        $prologue_markdown = wp_kses_post($prologue_markdown);
        $cover_image_url = esc_url_raw($cover_image_url);
        $is_extendable_db = $is_extendable === null ? null : ($is_extendable ? 1 : 0);
        $actor_user_id = max(0, (int) $actor_user_id);

        if ($book_id === '' || $title === '') {
            return ['ok' => false, 'message' => __('Book ID und Titel sind erforderlich.', 'crowdbook')];
        }

        $existing = $this->get_by_book_id($book_id);
        if (!$existing) {
            return ['ok' => false, 'message' => __('Buch nicht gefunden.', 'crowdbook')];
        }

        if ((string) ($existing->status ?? '') === 'active') {
            $pending_update_data = [
                'pending_title' => $title,
                'pending_description' => $description,
                'pending_prologue_markdown' => $prologue_markdown,
                'pending_cover_image_url' => $cover_image_url,
                'pending_status' => 'pending',
                'updated_at' => current_time('mysql'),
            ];
            $pending_update_format = ['%s', '%s', '%s', '%s', '%s', '%s'];
            if ($is_extendable_db !== null) {
                $pending_update_data['pending_is_extendable'] = $is_extendable_db;
                $pending_update_format[] = '%d';
            }
            if ((int) ($existing->created_by_user_id ?? 0) === 0 && $actor_user_id > 0) {
                $pending_update_data['created_by_user_id'] = $actor_user_id;
                $pending_update_format[] = '%d';
            }

            $pending_update = $wpdb->update(
                $this->table_name(),
                $pending_update_data,
                ['book_id' => $book_id],
                $pending_update_format,
                ['%s']
            );

            if ($pending_update === false) {
                return ['ok' => false, 'message' => __('Buchänderung konnte nicht eingereicht werden.', 'crowdbook')];
            }

            return ['ok' => true, 'message' => __('Buchänderung wurde eingereicht und wartet auf Freigabe. Live-Version bleibt sichtbar.', 'crowdbook')];
        }

        $update_data = [
            'title' => $title,
            'description' => $description,
            'prologue_markdown' => $prologue_markdown,
            'cover_image_url' => $cover_image_url,
            'pending_title' => null,
            'pending_description' => null,
            'pending_prologue_markdown' => null,
            'pending_cover_image_url' => null,
            'pending_is_extendable' => null,
            'pending_status' => 'none',
            'updated_at' => current_time('mysql'),
        ];
        $update_format = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];
        if ($is_extendable_db !== null) {
            $update_data['is_extendable'] = $is_extendable_db;
            $update_format[] = '%d';
        }
        if ((int) ($existing->created_by_user_id ?? 0) === 0 && $actor_user_id > 0) {
            $update_data['created_by_user_id'] = $actor_user_id;
            $update_format[] = '%d';
        }

        $updated = $wpdb->update(
            $this->table_name(),
            $update_data,
            ['book_id' => $book_id],
            $update_format,
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
            || trim((string) ($book->pending_cover_image_url ?? '')) !== ''
            || (($book->pending_is_extendable ?? null) !== null);
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
        if (($book->pending_is_extendable ?? null) !== null) {
            $preview->is_extendable = (int) $book->pending_is_extendable;
        }

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
                'is_extendable' => ($book->pending_is_extendable ?? null) !== null ? (int) $book->pending_is_extendable : (int) ($book->is_extendable ?? 1),
                'status' => 'active',
                'pending_title' => null,
                'pending_description' => null,
                'pending_prologue_markdown' => null,
                'pending_cover_image_url' => null,
                'pending_is_extendable' => null,
                'pending_status' => 'none',
                'updated_at' => current_time('mysql'),
            ],
            ['book_id' => $book_id],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%s']
        );

        return $updated !== false;
    }

    public function is_extendable(object $book): bool
    {
        return (int) ($book->is_extendable ?? 1) === 1;
    }

    public function is_owner(object $book, int $user_id): bool
    {
        return $user_id > 0 && (int) ($book->created_by_user_id ?? 0) === $user_id;
    }

    public function can_user_extend_book(object $book, int $user_id): bool
    {
        if ($this->is_extendable($book)) {
            return true;
        }

        return $this->is_owner($book, $user_id);
    }
}
