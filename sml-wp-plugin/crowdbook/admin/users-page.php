<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Admin_Users_Page
{
    private CrowdBook_Users $users;
    private CrowdBook_Chapters $chapters;

    public function __construct(CrowdBook_Users $users, CrowdBook_Chapters $chapters)
    {
        $this->users = $users;
        $this->chapters = $chapters;
    }

    public function handle_actions(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $action = isset($_GET['cb_user_action']) ? sanitize_key((string) $_GET['cb_user_action']) : '';
        $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field((string) $_GET['_wpnonce']) : '';

        if ($action === '' || $user_id <= 0) {
            return;
        }

        if (!wp_verify_nonce($nonce, 'crowdbook_admin_user_' . $user_id . '_' . $action)) {
            return;
        }

        if ($action === 'ban') {
            $this->users->update_status($user_id, 'banned');
        } elseif ($action === 'activate') {
            $this->users->update_status($user_id, 'active');
        }
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'crowdbook'));
        }

        $rows = $this->users->get_all_users();

        echo '<div class="wrap"><h1>CrowdBook – User</h1>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Display Name</th><th>Email</th><th>Status</th><th>Anzahl Kapitel</th><th>Registriert</th><th>Aktionen</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $chapters_count = $this->chapters->count_for_author((int) $row->id);

            $ban_url = wp_nonce_url(add_query_arg([
                'page' => 'crowdbook-users',
                'cb_user_action' => 'ban',
                'user_id' => (int) $row->id,
            ], admin_url('admin.php')), 'crowdbook_admin_user_' . (int) $row->id . '_ban');

            $activate_url = wp_nonce_url(add_query_arg([
                'page' => 'crowdbook-users',
                'cb_user_action' => 'activate',
                'user_id' => (int) $row->id,
            ], admin_url('admin.php')), 'crowdbook_admin_user_' . (int) $row->id . '_activate');

            echo '<tr>';
            echo '<td>' . esc_html((string) $row->display_name) . '</td>';
            echo '<td>' . esc_html((string) $row->email) . '</td>';
            echo '<td>' . esc_html((string) $row->status) . '</td>';
            echo '<td>' . (int) $chapters_count . '</td>';
            echo '<td>' . esc_html((string) $row->created_at) . '</td>';
            echo '<td>';
            echo '<a href="mailto:' . esc_attr((string) $row->email) . '">Email senden</a> | ';
            if ((string) $row->status === 'active') {
                echo '<a href="' . esc_url($ban_url) . '">Bannen</a>';
            } else {
                echo '<a href="' . esc_url($activate_url) . '">Aktivieren</a>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }
}
