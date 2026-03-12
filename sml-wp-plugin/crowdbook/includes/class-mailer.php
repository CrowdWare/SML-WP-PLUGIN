<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Mailer
{
    public function send_magic_link(string $email, string $link): bool
    {
        $subject = __('Dein Login Link', 'crowdbook');
        $body = sprintf(
            "%s\n\n%s\n\n%s",
            __('Hier ist dein Magic Link für CrowdBook:', 'crowdbook'),
            esc_url_raw($link),
            __('Der Link ist 15 Minuten gültig und nur einmal nutzbar.', 'crowdbook')
        );

        return (bool) wp_mail($email, $subject, $body);
    }

    public function send_first_chapter_live(string $email, string $chapter_title, string $url): bool
    {
        $subject = __('Dein Kapitel ist live!', 'crowdbook');
        $body = sprintf(
            "%s\n\n%s\n%s",
            __('Dein erstes Kapitel wurde veröffentlicht:', 'crowdbook'),
            $chapter_title,
            esc_url_raw($url)
        );

        return (bool) wp_mail($email, $subject, $body);
    }

    public function send_chapter_rejected(string $email, string $chapter_title): bool
    {
        $subject = __('Feedback zu deinem Kapitel', 'crowdbook');
        $body = sprintf(
            "%s\n\n%s",
            __('Dein Kapitel wurde leider nicht veröffentlicht. Bitte überarbeite es und reiche es erneut ein.', 'crowdbook'),
            $chapter_title
        );

        return (bool) wp_mail($email, $subject, $body);
    }

    public function send_like_milestone(string $email, int $count, string $chapter_title, string $url): bool
    {
        $subject = sprintf(__('%d Menschen haben deinen Weg gelesen', 'crowdbook'), $count);
        $body = sprintf(
            "%s\n\n%s\n%s\n%s",
            sprintf(__('Dein Kapitel hat %d Likes erreicht.', 'crowdbook'), $count),
            __('Kapitel:', 'crowdbook') . ' ' . $chapter_title,
            __('Link:', 'crowdbook') . ' ' . esc_url_raw($url),
            __('Danke fürs Teilen deiner Geschichte.', 'crowdbook')
        );

        return (bool) wp_mail($email, $subject, $body);
    }

    public function send_admin_spam_notice(string $chapter_title, string $email): bool
    {
        $admin_email = get_option('admin_email');
        if (!is_string($admin_email) || $admin_email === '') {
            return false;
        }

        $subject = __('Kapitel zur Prüfung', 'crowdbook');
        $body = sprintf(
            "%s\n\n%s\n%s",
            __('Ein neues Kapitel wurde als spam-verdächtig markiert.', 'crowdbook'),
            __('Titel:', 'crowdbook') . ' ' . $chapter_title,
            __('Autor Email:', 'crowdbook') . ' ' . $email
        );

        return (bool) wp_mail($admin_email, $subject, $body);
    }
}
