<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Users
{
    public function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'crowdbook_users';
    }

    public function ensure_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent()) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 30 * DAY_IN_SECONDS,
            'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public function find_by_email(string $email): ?object
    {
        global $wpdb;

        $email = sanitize_email($email);
        if ($email === '') {
            return null;
        }

        $table = $this->table_name();

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE email = %s LIMIT 1", $email));

        return is_object($row) ? $row : null;
    }

    public function create_if_missing(string $email, string $display_name = ''): ?object
    {
        global $wpdb;

        $email = sanitize_email($email);
        if ($email === '') {
            return null;
        }

        $existing = $this->find_by_email($email);
        if ($existing) {
            return $existing;
        }

        $display_name = sanitize_text_field($display_name);
        if ($display_name === '') {
            $display_name = sanitize_text_field(strstr($email, '@', true) ?: __('Anonymous Author', 'crowdbook'));
        }

        $table = $this->table_name();

        $inserted = $wpdb->insert(
            $table,
            [
                'email' => $email,
                'display_name' => $display_name,
                'bio' => '',
                'status' => 'active',
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        if (!$inserted) {
            return null;
        }

        return $this->find_by_email($email);
    }

    public function set_session_email(string $email): void
    {
        $this->ensure_session();
        $_SESSION['crowdbook_email'] = sanitize_email($email);
    }

    public function clear_session_email(): void
    {
        $this->ensure_session();
        unset($_SESSION['crowdbook_email']);
    }

    public function get_current_user(): ?object
    {
        $this->ensure_session();

        $email = isset($_SESSION['crowdbook_email']) ? sanitize_email((string) $_SESSION['crowdbook_email']) : '';
        if ($email === '') {
            return null;
        }

        $user = $this->find_by_email($email);
        if (!$user || (string) $user->status !== 'active') {
            return null;
        }

        return $user;
    }

    public function get_all_users(): array
    {
        global $wpdb;

        $table = $this->table_name();

        $sql = "SELECT * FROM {$table} ORDER BY created_at DESC";

        $rows = $wpdb->get_results($sql);

        return is_array($rows) ? $rows : [];
    }

    public function update_status(int $user_id, string $status): bool
    {
        global $wpdb;

        if (!in_array($status, ['active', 'banned'], true)) {
            return false;
        }

        $table = $this->table_name();

        $updated = $wpdb->update(
            $table,
            ['status' => $status],
            ['id' => $user_id],
            ['%s'],
            ['%d']
        );

        return $updated !== false;
    }
}
