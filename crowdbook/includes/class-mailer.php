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
            __('Hier ist dein Magic Link für CrowdBooks:', 'crowdbook'),
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

    public function send_chapter_rejected(string $email, string $chapter_title, string $feedback = ''): bool
    {
        $subject = __('Feedback zu deinem Kapitel', 'crowdbook');

        $feedback_text = $feedback !== ''
            ? $feedback
            : __('Bitte überarbeite es und reiche es erneut ein.', 'crowdbook');

        $body = sprintf(
            "%s\n\n%s\n\n%s\n\n%s",
            sprintf(__('Feedback zu deinem Kapitel: %s', 'crowdbook'), $chapter_title),
            __('Dein Kapitel wurde leider nicht veröffentlicht.', 'crowdbook'),
            $feedback_text,
            __('Du kannst es überarbeiten und erneut einreichen.', 'crowdbook')
        );

        return (bool) wp_mail($email, $subject, $body);
    }

    public function send_trusted_chapter_live(string $email, string $chapter_title, string $url): bool
    {
        $subject = __('Dein Kapitel ist direkt live!', 'crowdbook');
        $body = sprintf(
            "%s\n\n%s\n\n%s\n%s",
            sprintf(__('"%s" wurde ohne Moderation veröffentlicht.', 'crowdbook'), $chapter_title),
            __('Die Community hat dir ihr Vertrauen gegeben — deine Stimme ist sofort sichtbar.', 'crowdbook'),
            __('Link:', 'crowdbook') . ' ' . esc_url_raw($url),
            __('Danke, dass du schreibst.', 'crowdbook')
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

    public function send_admin_review_needed(string $chapter_title, string $book_id, string $email, bool $is_update = false): bool
    {
        $admin_email = get_option('admin_email');
        if (!is_string($admin_email) || $admin_email === '') {
            return false;
        }

        $subject = $is_update
            ? __('Kapitel-Update zur Prüfung', 'crowdbook')
            : __('Neues Kapitel zur Prüfung', 'crowdbook');
        $body = sprintf(
            "%s\n\n%s\n%s\n%s",
            __('Ein Kapitel wartet auf Moderation.', 'crowdbook'),
            __('Titel:', 'crowdbook') . ' ' . $chapter_title,
            __('Buch:', 'crowdbook') . ' ' . $book_id,
            __('Autor Email:', 'crowdbook') . ' ' . $email
        );

        return (bool) wp_mail($admin_email, $subject, $body);
    }

    public function send_admin_review_reminder(string $chapter_title, string $book_id, string $email, bool $is_update = false): bool
    {
        $admin_email = get_option('admin_email');
        if (!is_string($admin_email) || $admin_email === '') {
            return false;
        }

        $subject = $is_update
            ? __('Reminder: Kapitel-Update seit 5 Tagen offen', 'crowdbook')
            : __('Reminder: Kapitel seit 5 Tagen offen', 'crowdbook');
        $body = sprintf(
            "%s\n\n%s\n%s\n%s",
            __('Dieses Kapitel ist weiterhin in Prüfung und seit mindestens 5 Tagen offen.', 'crowdbook'),
            __('Titel:', 'crowdbook') . ' ' . $chapter_title,
            __('Buch:', 'crowdbook') . ' ' . $book_id,
            __('Autor Email:', 'crowdbook') . ' ' . $email
        );

        return (bool) wp_mail($admin_email, $subject, $body);
    }

    public function send_author_review_received(string $email, string $chapter_title): bool
    {
        $subject = __('Chapter received for review', 'crowdbook');
        $body = sprintf(
            "%s\n\n%s\n\n%s",
            __('Book will be reviewed.', 'crowdbook'),
            $chapter_title,
            __('Please send a reminder if this takes longer than 5 days.', 'crowdbook')
        );

        return (bool) wp_mail($email, $subject, $body);
    }

    public function send_author_review_reminder_sent(string $email, string $chapter_title): bool
    {
        $subject = __('Review reminder sent', 'crowdbook');
        $body = sprintf(
            "%s\n\n%s",
            __('Your chapter is still under review. A reminder has been sent to the admin team.', 'crowdbook'),
            $chapter_title
        );

        return (bool) wp_mail($email, $subject, $body);
    }
}
