<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Likes
{
    private CrowdBook_Users $users;
    private CrowdBook_Chapters $chapters;
    private CrowdBook_Mailer $mailer;

    public function __construct(CrowdBook_Users $users, CrowdBook_Chapters $chapters, CrowdBook_Mailer $mailer)
    {
        $this->users = $users;
        $this->chapters = $chapters;
        $this->mailer = $mailer;
    }

    public function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'crowdbook_likes';
    }

    public function register_ajax(): void
    {
        add_action('wp_ajax_crowdbook_like', [$this, 'ajax_like']);
    }

    public function ajax_like(): void
    {
        check_ajax_referer('crowdbook_like_nonce', 'nonce');

        $chapter_id = isset($_POST['chapter_id']) ? (int) $_POST['chapter_id'] : 0;
        if ($chapter_id <= 0) {
            wp_send_json_error(['message' => __('Ungültige Kapitel-ID.', 'crowdbook')], 400);
        }

        $user = $this->users->get_current_user();
        if (!$user) {
            wp_send_json_error(['message' => __('Bitte zuerst einloggen, um zu liken.', 'crowdbook')], 403);
        }

        $result = $this->add_like($chapter_id, (int) $user->id);
        if (!$result['ok']) {
            wp_send_json_error(['message' => $result['message'], 'count' => $result['count']], 400);
        }

        wp_send_json_success(['count' => $result['count']]);
    }

    public function add_like(int $chapter_id, int $user_id): array
    {
        global $wpdb;

        if ($user_id <= 0) {
            return ['ok' => false, 'message' => __('Like aktuell nicht möglich.', 'crowdbook'), 'count' => 0];
        }

        $chapter = $this->chapters->get_by_id($chapter_id);
        if (!$chapter || (string) $chapter->status !== 'published') {
            return ['ok' => false, 'message' => __('Kapitel nicht verfügbar.', 'crowdbook'), 'count' => 0];
        }

        $table = $this->table_name();

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE chapter_id = %d AND user_id = %d LIMIT 1",
            $chapter_id,
            $user_id
        ));

        if ($existing) {
            return ['ok' => false, 'message' => __('Du hast dieses Kapitel bereits geliked.', 'crowdbook'), 'count' => (int) $chapter->like_count];
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'chapter_id' => $chapter_id,
                'user_id' => $user_id,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s']
        );

        if (!$inserted) {
            return ['ok' => false, 'message' => __('Like konnte nicht gespeichert werden.', 'crowdbook'), 'count' => (int) $chapter->like_count];
        }

        $new_count = (int) $chapter->like_count + 1;

        $wpdb->update(
            $this->chapters->table_name(),
            ['like_count' => $new_count],
            ['id' => $chapter_id],
            ['%d'],
            ['%d']
        );

        $this->send_milestone_email_if_needed($chapter, $new_count);

        return ['ok' => true, 'message' => __('Like gespeichert.', 'crowdbook'), 'count' => $new_count];
    }

    public function has_user_liked(int $chapter_id, int $user_id): bool
    {
        global $wpdb;

        if ($chapter_id <= 0 || $user_id <= 0) {
            return false;
        }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name()} WHERE chapter_id = %d AND user_id = %d LIMIT 1",
            $chapter_id,
            $user_id
        ));

        return !empty($exists);
    }

    private function send_milestone_email_if_needed(object $chapter, int $like_count): void
    {
        $milestones = [1, 5, 10, 25, 50, 100];
        if (!in_array($like_count, $milestones, true)) {
            return;
        }

        $author_email = $this->chapters->get_author_email((int) $chapter->author_id);
        if ($author_email === '') {
            return;
        }

        $this->mailer->send_like_milestone(
            $author_email,
            $like_count,
            (string) $chapter->title,
            $this->chapters->chapter_url((string) $chapter->slug)
        );
    }

}
