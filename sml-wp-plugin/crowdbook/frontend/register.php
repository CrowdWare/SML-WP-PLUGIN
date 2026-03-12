<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Frontend_Register
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crowdbook_register_submit'])) {
            if (!isset($_POST['crowdbook_register_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['crowdbook_register_nonce'])), 'crowdbook_register')) {
                $message = '<div class="crowdbook-notice error">' . esc_html__('Ungültige Anfrage.', 'crowdbook') . '</div>';
            } else {
                $display_name = sanitize_text_field((string) wp_unslash($_POST['display_name'] ?? ''));
                if ($display_name === '') {
                    $message = '<div class="crowdbook-notice error">' . esc_html__('Bitte gib einen Anzeigenamen ein.', 'crowdbook') . '</div>';
                } else {
                    $result = $this->auth->handle_login_request([
                        'email' => sanitize_email((string) wp_unslash($_POST['email'] ?? '')),
                        'display_name' => $display_name,
                    ]);
                    $class = $result['ok'] ? 'success' : 'error';
                    $message = '<div class="crowdbook-notice ' . esc_attr($class) . '">' . esc_html((string) $result['message']) . '</div>';
                }
            }
        }

        ob_start();
        echo '<div class="crowdbook-login">';
        echo '<h3>' . esc_html__('Registrieren', 'crowdbook') . '</h3>';
        echo wp_kses_post($message);
        echo '<form method="post">';
        wp_nonce_field('crowdbook_register', 'crowdbook_register_nonce');
        echo '<p><label>' . esc_html__('Display Name', 'crowdbook') . '</label><input type="text" name="display_name" required /></p>';
        echo '<p><label>' . esc_html__('Email', 'crowdbook') . '</label><input type="email" name="email" required /></p>';
        echo '<p><button type="submit" name="crowdbook_register_submit" value="1">' . esc_html__('Registrieren & Magic Link senden', 'crowdbook') . '</button></p>';
        echo '</form>';
        echo '<p><a href="' . esc_url(home_url('/login')) . '">' . esc_html__('Schon registriert? Zum Login', 'crowdbook') . '</a></p>';
        echo '</div>';

        return (string) ob_get_clean();
    }
}
