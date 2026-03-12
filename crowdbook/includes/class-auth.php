<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Auth
{
    private CrowdBook_Users $users;
    private CrowdBook_Mailer $mailer;

    public function __construct(CrowdBook_Users $users, CrowdBook_Mailer $mailer)
    {
        $this->users = $users;
        $this->mailer = $mailer;
    }

    public function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'crowdbook_magic_tokens';
    }

    public function handle_login_request(array $payload): array
    {
        $email = sanitize_email($payload['email'] ?? '');
        $display_name = sanitize_text_field($payload['display_name'] ?? '');

        if ($email === '' || !is_email($email)) {
            return ['ok' => false, 'message' => __('Bitte gib eine gültige Email-Adresse ein.', 'crowdbook')];
        }

        if (!$this->check_rate_limit($email)) {
            return ['ok' => false, 'message' => __('Zu viele Magic-Link-Anfragen. Bitte versuche es später erneut.', 'crowdbook')];
        }

        $user = $this->users->create_if_missing($email, $display_name);
        if (!$user) {
            return ['ok' => false, 'message' => __('Account konnte nicht erstellt werden.', 'crowdbook')];
        }

        if ((string) $user->status !== 'active') {
            return ['ok' => false, 'message' => __('Dieser Account ist gesperrt.', 'crowdbook')];
        }

        $token = bin2hex(random_bytes(32));
        $expires = gmdate('Y-m-d H:i:s', time() + 900);

        global $wpdb;
        $inserted = $wpdb->insert(
            $this->table_name(),
            [
                'email' => $email,
                'token' => $token,
                'expires_at' => $expires,
                'used' => 0,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['%s', '%s', '%s', '%d', '%s']
        );

        if (!$inserted) {
            return ['ok' => false, 'message' => __('Magic Link konnte nicht erzeugt werden.', 'crowdbook')];
        }

        $link = add_query_arg('token', rawurlencode($token), home_url('/crowdbook/auth'));
        $this->mailer->send_magic_link($email, $link);

        return ['ok' => true, 'message' => __('Magic Link wurde per Email versendet.', 'crowdbook')];
    }

    public function validate_token(string $token): bool
    {
        global $wpdb;

        $token = preg_replace('/[^a-f0-9]/', '', strtolower($token));
        if (!is_string($token) || strlen($token) !== 64) {
            return false;
        }

        $table = $this->table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE token = %s AND used = 0 AND expires_at > UTC_TIMESTAMP() LIMIT 1",
                $token
            )
        );

        if (!$row) {
            return false;
        }

        $wpdb->update(
            $table,
            ['used' => 1],
            ['id' => (int) $row->id],
            ['%d'],
            ['%d']
        );

        $this->users->set_session_email((string) $row->email);

        return true;
    }

    private function check_rate_limit(string $email): bool
    {
        $key = 'crowdbook_magic_rl_' . md5(strtolower($email));
        $count = (int) get_transient($key);

        if ($count >= 3) {
            return false;
        }

        set_transient($key, $count + 1, HOUR_IN_SECONDS);

        return true;
    }
}
