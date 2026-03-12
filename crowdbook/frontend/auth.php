<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Frontend_Auth
{
    public function render_result(bool $ok): void
    {
        status_header($ok ? 200 : 400);
        nocache_headers();

        $title = $ok ? __('Login erfolgreich', 'crowdbook') : __('Login fehlgeschlagen', 'crowdbook');
        $message = $ok
            ? __('Du bist jetzt eingeloggt. Du kannst dieses Fenster schließen.', 'crowdbook')
            : __('Der Login-Link ist ungültig oder abgelaufen.', 'crowdbook');

        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . esc_html($title) . '</title>';
        echo '</head><body style="font-family:sans-serif;padding:2rem;">';
        echo '<h1>' . esc_html($title) . '</h1>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '<p><a href="' . esc_url(home_url('/')) . '">' . esc_html__('Zur Startseite', 'crowdbook') . '</a></p>';
        echo '</body></html>';
    }
}
