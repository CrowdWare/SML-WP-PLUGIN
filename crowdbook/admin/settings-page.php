<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Admin_Settings_Page
{
    private CrowdBook_Reputation $reputation;

    public function __construct(CrowdBook_Reputation $reputation)
    {
        $this->reputation = $reputation;
    }

    public function handle_actions(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (
            $_SERVER['REQUEST_METHOD'] !== 'POST'
            || !isset($_POST['crowdbook_settings_nonce'])
        ) {
            return;
        }

        if (!wp_verify_nonce(
            sanitize_text_field((string) wp_unslash($_POST['crowdbook_settings_nonce'])),
            'crowdbook_settings'
        )) {
            return;
        }

        $min_chapters = isset($_POST['trust_min_chapters']) ? (int) $_POST['trust_min_chapters'] : 5;
        $min_likes    = isset($_POST['trust_min_likes']) ? (int) $_POST['trust_min_likes'] : 10;

        $this->reputation->save_thresholds($min_chapters, $min_likes);
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'crowdbook'));
        }

        $thresholds = $this->reputation->get_thresholds();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('CrowdBooks – Einstellungen', 'crowdbook') . '</h1>';

        echo '<h2>' . esc_html__('Vertrauen & Reputation', 'crowdbook') . '</h2>';
        echo '<p style="max-width:600px;font-style:italic;color:#555;">';
        echo esc_html__('„Ich bin nicht deiner Meinung — aber ich würde alles dafür tun, dass du gehört wirst."', 'crowdbook');
        echo '</p>';
        echo '<p style="max-width:600px;">';
        echo esc_html__('Autoren, die dieser Community bereits etwas gegeben haben, brauchen keine Erlaubnis mehr. Sie haben sie sich verdient. Lege hier fest, ab wann ein Autor direkt ohne Moderation veröffentlicht.', 'crowdbook');
        echo '</p>';

        echo '<form method="post">';
        wp_nonce_field('crowdbook_settings', 'crowdbook_settings_nonce');

        echo '<table class="form-table" role="presentation">';

        echo '<tr>';
        echo '<th scope="row"><label for="trust_min_chapters">';
        echo esc_html__('Veröffentlichte Kapitel (Mindest)', 'crowdbook');
        echo '</label></th>';
        echo '<td>';
        echo '<input type="number" id="trust_min_chapters" name="trust_min_chapters" value="' . (int) $thresholds['min_chapters'] . '" min="1" class="small-text" />';
        echo '<p class="description">' . esc_html__('Wie viele Kapitel muss ein Autor veröffentlicht haben? Neue Autoren durchlaufen immer die Moderation.', 'crowdbook') . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="trust_min_likes">';
        echo esc_html__('Gesammelte Likes (Mindest)', 'crowdbook');
        echo '</label></th>';
        echo '<td>';
        echo '<input type="number" id="trust_min_likes" name="trust_min_likes" value="' . (int) $thresholds['min_likes'] . '" min="0" class="small-text" />';
        echo '<p class="description">' . esc_html__('Wie viele Likes müssen die veröffentlichten Kapitel insgesamt haben? Die Community entscheidet — nicht das System.', 'crowdbook') . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '</table>';

        submit_button(esc_html__('Speichern', 'crowdbook'));
        echo '</form>';
        echo '</div>';
    }
}
