<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Admin_Dashboard_Page
{
    private CrowdBook_Books $books;
    private CrowdBook_Chapters $chapters;

    public function __construct(CrowdBook_Books $books, CrowdBook_Chapters $chapters)
    {
        $this->books = $books;
        $this->chapters = $chapters;
    }

    public function render(): void
    {
        if (!current_user_can('moderate_crowdbook')) {
            wp_die(esc_html__('Keine Berechtigung.', 'crowdbook'));
        }

        $books = $this->books->get_all();
        $chapter_count = count($this->chapters->get_all());

        echo '<div class="wrap">';
        echo '<h1>CrowdBook – Übersicht</h1>';
        echo '<p>' . esc_html__('Plugin-Seiten sind direkt verfügbar über /books, /editor, /dashboard, /login und /register.', 'crowdbook') . '</p>';
        echo '<p>' . esc_html__('Shortcodes bleiben optional, falls du Inhalte in normale WordPress-Seiten einbetten willst.', 'crowdbook') . '</p>';

        echo '<h2>Shortcodes</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Shortcode</th><th>Funktion</th></tr></thead><tbody>';
        echo '<tr><td><code>[crowdbook_login]</code></td><td>Magic-Link Login/Registrierung</td></tr>';
        echo '<tr><td><code>[crowdbook_register]</code></td><td>Registrierung mit Display Name + Magic Link</td></tr>';
        echo '<tr><td><code>[crowdbook_dashboard]</code></td><td>Autoren-Dashboard</td></tr>';
        echo '<tr><td><code>[crowdbook_editor]</code></td><td>Kapitel-Editor (inkl. Bild-Upload)</td></tr>';
        echo '<tr><td><code>[crowdbook_index book="mein-buch"]</code></td><td>Buch-Index eines Buches</td></tr>';
        echo '<tr><td><code>[crowdbook_books]</code></td><td>Liste aller aktiven Bücher</td></tr>';
        echo '</tbody></table>';

        echo '<h2>Status</h2>';
        echo '<ul style="list-style:disc;padding-left:20px;">';
        echo '<li><strong>Bücher:</strong> ' . (int) count($books) . '</li>';
        echo '<li><strong>Kapitel gesamt:</strong> ' . (int) $chapter_count . '</li>';
        echo '</ul>';

        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=crowdbook-books')) . '">Bücher verwalten</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=crowdbook-chapters')) . '">Kapitel moderieren</a></p>';
        echo '</div>';
    }
}
