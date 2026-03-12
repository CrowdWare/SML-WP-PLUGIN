<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Chapters
{
    private CrowdBook_Users $users;
    private CrowdBook_Spam_Filter $spam_filter;
    private CrowdBook_SML_Renderer $sml_renderer;
    private CrowdBook_Mailer $mailer;

    public function __construct(
        CrowdBook_Users $users,
        CrowdBook_Spam_Filter $spam_filter,
        CrowdBook_SML_Renderer $sml_renderer,
        CrowdBook_Mailer $mailer
    ) {
        $this->users = $users;
        $this->spam_filter = $spam_filter;
        $this->sml_renderer = $sml_renderer;
        $this->mailer = $mailer;
    }

    public function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'crowdbook_chapters';
    }

    public function create_or_update_draft(
        int $author_id,
        string $title,
        string $path_label,
        string $markdown,
        string $book_id = '',
        ?int $chapter_id = null
    ): array
    {
        global $wpdb;

        $title = sanitize_text_field($title);
        $path_label = sanitize_text_field($path_label);
        $markdown = wp_kses_post($markdown);
        $book_id = sanitize_key($book_id);
        if ($book_id === '') {
            return ['ok' => false, 'message' => __('Bitte wähle ein Buch aus.', 'crowdbook')];
        }

        if ($title === '' || trim($markdown) === '') {
            return ['ok' => false, 'message' => __('Titel und Inhalt sind erforderlich.', 'crowdbook')];
        }

        $table = $this->table_name();

        if ($chapter_id && $chapter_id > 0) {
            $existing = $this->get_by_id($chapter_id);
            if (!$existing || (int) $existing->author_id !== $author_id) {
                return ['ok' => false, 'message' => __('Kapitel nicht gefunden.', 'crowdbook')];
            }

            if ((string) $existing->status === 'published') {
                // Keep the live chapter untouched and stage edits for moderation.
                $updated = $wpdb->update(
                    $table,
                    [
                        'pending_title' => $title,
                        'pending_path_label' => $path_label,
                        'pending_markdown_content' => $markdown,
                        'pending_status' => 'draft',
                        'pending_spam_score' => 0.0,
                    ],
                    ['id' => $chapter_id],
                    ['%s', '%s', '%s', '%s', '%f'],
                    ['%d']
                );

                return [
                    'ok' => $updated !== false,
                    'message' => $updated !== false
                        ? __('Änderungsentwurf gespeichert. Live-Version bleibt veröffentlicht.', 'crowdbook')
                        : __('Änderungsentwurf konnte nicht gespeichert werden.', 'crowdbook'),
                    'chapter_id' => $chapter_id,
                ];
            }

            $updated = $wpdb->update(
                $table,
                [
                    'book_id' => $book_id,
                    'title' => $title,
                    'path_label' => $path_label,
                    'markdown_content' => $markdown,
                    'status' => 'draft',
                    'pending_title' => null,
                    'pending_path_label' => null,
                    'pending_markdown_content' => null,
                    'pending_status' => 'none',
                    'pending_spam_score' => 0.0,
                ],
                ['id' => $chapter_id],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f'],
                ['%d']
            );

            return [
                'ok' => $updated !== false,
                'message' => $updated !== false ? __('Entwurf gespeichert.', 'crowdbook') : __('Entwurf konnte nicht gespeichert werden.', 'crowdbook'),
                'chapter_id' => $chapter_id,
            ];
        }

        $slug = $this->generate_unique_slug($title);

        $inserted = $wpdb->insert(
            $table,
            [
                'book_id' => $book_id,
                'author_id' => $author_id,
                'title' => $title,
                'slug' => $slug,
                'path_label' => $path_label,
                'markdown_content' => $markdown,
                'status' => 'draft',
                'spam_score' => 0.0,
                'pending_status' => 'none',
                'pending_spam_score' => 0.0,
                'like_count' => 0,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%f', '%d', '%s']
        );

        if (!$inserted) {
            return ['ok' => false, 'message' => __('Entwurf konnte nicht erstellt werden.', 'crowdbook')];
        }

        return ['ok' => true, 'message' => __('Entwurf gespeichert.', 'crowdbook'), 'chapter_id' => (int) $wpdb->insert_id];
    }

    public function submit(int $chapter_id, int $author_id): array
    {
        global $wpdb;

        $chapter = $this->get_by_id($chapter_id);
        if (!$chapter || (int) $chapter->author_id !== $author_id) {
            return ['ok' => false, 'message' => __('Kapitel nicht gefunden.', 'crowdbook')];
        }

        $is_live_published = (string) $chapter->status === 'published';
        $pending_markdown = trim((string) ($chapter->pending_markdown_content ?? ''));

        if ($is_live_published) {
            if ($pending_markdown === '') {
                return ['ok' => false, 'message' => __('Bitte speichere zuerst einen Änderungsentwurf.', 'crowdbook')];
            }

            $analysis = $this->spam_filter->analyze($pending_markdown);

            if ($analysis['is_spam']) {
                $wpdb->update(
                    $this->table_name(),
                    [
                        'pending_status' => 'rejected',
                        'pending_spam_score' => $analysis['score'],
                    ],
                    ['id' => $chapter_id],
                    ['%s', '%f'],
                    ['%d']
                );

                $author_email = (string) $this->get_author_email((int) $chapter->author_id);
                $mail_title = (string) ($chapter->pending_title ?: $chapter->title);
                if ($author_email !== '') {
                    $this->mailer->send_chapter_rejected($author_email, $mail_title);
                }
                $this->mailer->send_admin_spam_notice($mail_title, $author_email);

                return ['ok' => true, 'message' => __('Änderung wurde abgelehnt (Spam-Verdacht). Live-Version bleibt sichtbar.', 'crowdbook'), 'status' => 'rejected'];
            }

            $wpdb->update(
                $this->table_name(),
                [
                    'pending_status' => 'pending',
                    'pending_spam_score' => $analysis['score'],
                ],
                ['id' => $chapter_id],
                ['%s', '%f'],
                ['%d']
            );

            return ['ok' => true, 'message' => __('Änderung wurde zur Moderation eingereicht. Live-Version bleibt sichtbar.', 'crowdbook'), 'status' => 'pending'];
        }

        $analysis = $this->spam_filter->analyze((string) $chapter->markdown_content);

        if ($analysis['is_spam']) {
            $wpdb->update(
                $this->table_name(),
                [
                    'status' => 'rejected',
                    'spam_score' => $analysis['score'],
                ],
                ['id' => $chapter_id],
                ['%s', '%f'],
                ['%d']
            );

            $author_email = (string) $this->get_author_email((int) $chapter->author_id);
            if ($author_email !== '') {
                $this->mailer->send_chapter_rejected($author_email, (string) $chapter->title);
            }

            $this->mailer->send_admin_spam_notice((string) $chapter->title, $author_email);

            return ['ok' => true, 'message' => __('Kapitel wurde zur Moderation markiert.', 'crowdbook'), 'status' => 'rejected'];
        }

        $wpdb->update(
            $this->table_name(),
            [
                'status' => 'pending',
                'spam_score' => $analysis['score'],
            ],
            ['id' => $chapter_id],
            ['%s', '%f'],
            ['%d']
        );

        return ['ok' => true, 'message' => __('Kapitel wurde zur Moderation eingereicht.', 'crowdbook'), 'status' => 'pending'];
    }

    public function moderate_status(int $chapter_id, string $status): bool
    {
        global $wpdb;

        if (!in_array($status, ['draft', 'pending', 'published', 'rejected'], true)) {
            return false;
        }

        $chapter = $this->get_by_id($chapter_id);
        if (!$chapter) {
            return false;
        }

        $is_live_published = (string) $chapter->status === 'published';
        $has_pending_update = in_array((string) ($chapter->pending_status ?? 'none'), ['draft', 'pending', 'rejected'], true)
            && (string) ($chapter->pending_markdown_content ?? '') !== '';

        if ($is_live_published && $has_pending_update) {
            if ($status === 'published') {
                $result = $wpdb->update(
                    $this->table_name(),
                    [
                        'title' => (string) ($chapter->pending_title ?? $chapter->title),
                        'path_label' => $chapter->pending_path_label !== null ? (string) $chapter->pending_path_label : (string) $chapter->path_label,
                        'markdown_content' => (string) ($chapter->pending_markdown_content ?? $chapter->markdown_content),
                        'spam_score' => (float) ($chapter->pending_spam_score ?? $chapter->spam_score),
                        'status' => 'published',
                        'published_at' => current_time('mysql'),
                        'pending_title' => null,
                        'pending_path_label' => null,
                        'pending_markdown_content' => null,
                        'pending_status' => 'none',
                        'pending_spam_score' => 0.0,
                    ],
                    ['id' => $chapter_id],
                    ['%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%f'],
                    ['%d']
                );

                if ($result !== false) {
                    $updated_chapter = $this->get_by_id($chapter_id);
                    if ($updated_chapter) {
                        $html = $this->build_chapter_body_html($updated_chapter);
                        $this->sml_renderer->write_static_html((string) $updated_chapter->slug, $html);
                    }
                }

                return $result !== false;
            }

            if ($status === 'rejected') {
                $result = $wpdb->update(
                    $this->table_name(),
                    [
                        'pending_status' => 'rejected',
                    ],
                    ['id' => $chapter_id],
                    ['%s'],
                    ['%d']
                );

                return $result !== false;
            }

            if ($status === 'pending' || $status === 'draft') {
                $result = $wpdb->update(
                    $this->table_name(),
                    [
                        'pending_status' => $status,
                    ],
                    ['id' => $chapter_id],
                    ['%s'],
                    ['%d']
                );

                return $result !== false;
            }
        }

        $data = [
            'status' => $status,
        ];
        $format = ['%s'];

        if ($status === 'published') {
            $data['published_at'] = current_time('mysql');
            $format[] = '%s';
        }

        $result = $wpdb->update(
            $this->table_name(),
            $data,
            ['id' => $chapter_id],
            $format,
            ['%d']
        );

        if ($status === 'published') {
            $updated_chapter = $this->get_by_id($chapter_id);
            if ($updated_chapter) {
                $html = $this->build_chapter_body_html($updated_chapter);
                $this->sml_renderer->write_static_html((string) $updated_chapter->slug, $html);
            }

            $author_email = (string) $this->get_author_email((int) $chapter->author_id);
            $published_count = $this->count_published_by_author((int) $chapter->author_id);
            if ($published_count === 1 && $author_email !== '') {
                $this->mailer->send_first_chapter_live($author_email, (string) $chapter->title, $this->chapter_url((string) $chapter->slug));
            }
        }

        return $result !== false;
    }

    public function delete_chapter(int $chapter_id): bool
    {
        global $wpdb;

        $result = $wpdb->delete($this->table_name(), ['id' => $chapter_id], ['%d']);

        return $result !== false;
    }

    public function get_by_id(int $id): ?object
    {
        global $wpdb;

        $table = $this->table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id));

        return is_object($row) ? $row : null;
    }

    public function get_by_slug(string $slug): ?object
    {
        global $wpdb;

        $slug = sanitize_title($slug);
        if ($slug === '') {
            return null;
        }

        $table = $this->table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s LIMIT 1", $slug));

        return is_object($row) ? $row : null;
    }

    public function get_all(?string $status = null, ?string $book_id = null): array
    {
        global $wpdb;

        $table = $this->table_name();

        $status_valid = $status && in_array($status, ['draft', 'pending', 'updated', 'published', 'rejected'], true);
        $book_key = is_string($book_id) ? sanitize_key($book_id) : '';
        $book_valid = $book_key !== '';

        if ($status === 'updated' && $book_valid) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = 'published' AND pending_status IN ('draft','pending','rejected') AND pending_markdown_content IS NOT NULL AND pending_markdown_content <> '' AND book_id = %s ORDER BY created_at DESC",
                $book_key
            );
            $rows = $wpdb->get_results($sql);
            return is_array($rows) ? $rows : [];
        }

        if ($status === 'updated') {
            $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE status = 'published' AND pending_status IN ('draft','pending','rejected') AND pending_markdown_content IS NOT NULL AND pending_markdown_content <> '' ORDER BY created_at DESC");
            return is_array($rows) ? $rows : [];
        }

        if ($status === 'pending' && $book_valid) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE (status = 'pending' OR (status = 'published' AND pending_status = 'pending')) AND book_id = %s ORDER BY created_at DESC",
                $book_key
            );
            $rows = $wpdb->get_results($sql);
            return is_array($rows) ? $rows : [];
        }

        if ($status === 'pending') {
            $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE status = 'pending' OR (status = 'published' AND pending_status = 'pending') ORDER BY created_at DESC");
            return is_array($rows) ? $rows : [];
        }

        if ($status_valid && $book_valid) {
            $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE status = %s AND book_id = %s ORDER BY created_at DESC", $status, $book_key);
            $rows = $wpdb->get_results($sql);
            return is_array($rows) ? $rows : [];
        }

        if ($status_valid) {
            $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC", $status);
            $rows = $wpdb->get_results($sql);
            return is_array($rows) ? $rows : [];
        }

        if ($book_valid) {
            $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE book_id = %s ORDER BY created_at DESC", $book_key);
            $rows = $wpdb->get_results($sql);
            return is_array($rows) ? $rows : [];
        }

        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");

        return is_array($rows) ? $rows : [];
    }

    public function get_published(string $book_id = 'choose-your-incarnation'): array
    {
        global $wpdb;

        $table = $this->table_name();

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'published' AND book_id = %s ORDER BY published_at DESC, created_at DESC",
            $book_id
        );

        $rows = $wpdb->get_results($sql);

        return is_array($rows) ? $rows : [];
    }

    public function get_for_author(int $author_id): array
    {
        global $wpdb;

        $table = $this->table_name();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE author_id = %d ORDER BY created_at DESC", $author_id));

        return is_array($rows) ? $rows : [];
    }

    public function count_published_by_author(int $author_id): int
    {
        global $wpdb;

        $table = $this->table_name();

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE author_id = %d AND status = 'published'",
            $author_id
        ));

        return (int) $count;
    }

    public function count_for_author(int $author_id): int
    {
        global $wpdb;

        $table = $this->table_name();
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE author_id = %d", $author_id));

        return (int) $count;
    }

    public function chapter_url(string $slug): string
    {
        return home_url('/crowdbook/chapter/' . rawurlencode($slug));
    }

    public function build_chapter_body_html(object $chapter): string
    {
        $markdown_html = $this->sml_renderer->render_markdown_html((string) $chapter->markdown_content);
        $title = esc_html((string) $chapter->title);
        $path_label = esc_html((string) ($chapter->path_label ?? ''));
        $author = esc_html($this->get_author_name((int) $chapter->author_id));

        $html = '<article class="crowdbook-chapter">';
        $html .= '<header class="crowdbook-chapter-head">';
        $html .= '<h1>' . $title . '</h1>';
        $html .= '<p class="crowdbook-meta">' . sprintf(__('Von %1$s · Pfad: %2$s', 'crowdbook'), $author, $path_label) . '</p>';
        $html .= '</header>';
        $html .= '<div class="crowdbook-markdown">' . $markdown_html . '</div>';
        $html .= '</article>';

        return $html;
    }

    public function get_author_name(int $author_id): string
    {
        global $wpdb;

        $table = $this->users->table_name();
        $name = $wpdb->get_var($wpdb->prepare("SELECT display_name FROM {$table} WHERE id = %d", $author_id));

        return is_string($name) && $name !== '' ? $name : __('Unbekannt', 'crowdbook');
    }

    public function get_author_email(int $author_id): string
    {
        global $wpdb;

        $table = $this->users->table_name();
        $email = $wpdb->get_var($wpdb->prepare("SELECT email FROM {$table} WHERE id = %d", $author_id));

        return is_string($email) ? $email : '';
    }

    private function generate_unique_slug(string $title): string
    {
        global $wpdb;

        $base = sanitize_title($title);
        if ($base === '') {
            $base = 'chapter';
        }

        $slug = $base;
        $i = 2;

        while (true) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_name()} WHERE slug = %s LIMIT 1", $slug));
            if (!$exists) {
                return $slug;
            }
            $slug = $base . '-' . $i;
            $i++;
        }
    }
}
