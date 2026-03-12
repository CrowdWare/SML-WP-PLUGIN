<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Frontend_Login
{
    private CrowdBook_Auth $auth;
    private CrowdBook_Users $users;

    public function __construct(CrowdBook_Auth $auth, CrowdBook_Users $users)
    {
        $this->auth = $auth;
        $this->users = $users;
    }

    public function render(): string
    {
        $current = $this->users->get_current_user();
        if ($current) {
            return '<p>' . esc_html__('Du bist bereits eingeloggt.', 'crowdbook') . '</p>';
        }

        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crowdbook_login_submit'])) {
            if (!isset($_POST['crowdbook_login_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['crowdbook_login_nonce'])), 'crowdbook_login')) {
                $message = '<div class="crowdbook-notice error">' . esc_html__('Ungültige Anfrage.', 'crowdbook') . '</div>';
            } else {
                $result = $this->auth->handle_login_request([
                    'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
                    'display_name' => sanitize_text_field(wp_unslash($_POST['display_name'] ?? '')),
                ]);
                $class = $result['ok'] ? 'success' : 'error';
                $message = '<div class="crowdbook-notice ' . esc_attr($class) . '">' . esc_html($result['message']) . '</div>';
            }
        }

        ob_start();
        echo '<div class="crowdbook-login">';
        echo wp_kses_post($message);
        echo '<form method="post">';
        wp_nonce_field('crowdbook_login', 'crowdbook_login_nonce');
        echo '<p><label>' . esc_html__('Email', 'crowdbook') . '</label><input type="email" name="email" required /></p>';
        echo '<p><button type="submit" name="crowdbook_login_submit" value="1">' . esc_html__('Magic Link senden', 'crowdbook') . '</button></p>';
        echo '</form>';
        echo '<p><a href="' . esc_url(home_url('/register')) . '">' . esc_html__('Neu hier? Zur Registrierung', 'crowdbook') . '</a></p>';
        echo '</div>';

        return (string) ob_get_clean();
    }
}
