<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CrowdBook_Reputation
 *
 * Vertrauen entsteht nicht durch Erlaubnis — sondern durch Beweis.
 * Wer der Community fünf Kapitel gegeben hat, die Menschen berühren,
 * braucht keine Erlaubnis mehr für das sechste.
 *
 * "I disapprove of what you say, but I will defend to the death
 *  your right to say it." — Evelyn Beatrice Hall, über Voltaire
 */
class CrowdBook_Reputation
{
    private const OPT_MIN_CHAPTERS = 'crowdbook_trust_min_chapters';
    private const OPT_MIN_LIKES    = 'crowdbook_trust_min_likes';

    public function get_thresholds(): array
    {
        return [
            'min_chapters' => max(1, (int) get_option(self::OPT_MIN_CHAPTERS, 5)),
            'min_likes'    => max(0, (int) get_option(self::OPT_MIN_LIKES, 10)),
        ];
    }

    public function save_thresholds(int $min_chapters, int $min_likes): void
    {
        update_option(self::OPT_MIN_CHAPTERS, max(1, $min_chapters));
        update_option(self::OPT_MIN_LIKES, max(0, $min_likes));
    }

    public function get_author_stats(int $author_id): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'crowdbook_chapters';

        $published = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE author_id = %d AND status = 'published'",
            $author_id
        ));

        $total_likes = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(like_count), 0) FROM {$table} WHERE author_id = %d AND status = 'published'",
            $author_id
        ));

        return [
            'published_chapters' => $published,
            'total_likes'        => $total_likes,
        ];
    }

    public function is_trusted(int $author_id): bool
    {
        $thresholds = $this->get_thresholds();
        $stats      = $this->get_author_stats($author_id);

        return $stats['published_chapters'] >= $thresholds['min_chapters']
            && $stats['total_likes'] >= $thresholds['min_likes'];
    }

    public function progress(int $author_id): array
    {
        $thresholds = $this->get_thresholds();
        $stats      = $this->get_author_stats($author_id);
        $trusted    = $stats['published_chapters'] >= $thresholds['min_chapters']
            && $stats['total_likes'] >= $thresholds['min_likes'];

        return [
            'published_chapters' => $stats['published_chapters'],
            'total_likes'        => $stats['total_likes'],
            'min_chapters'       => $thresholds['min_chapters'],
            'min_likes'          => $thresholds['min_likes'],
            'trusted'            => $trusted,
            'chapters_missing'   => max(0, $thresholds['min_chapters'] - $stats['published_chapters']),
            'likes_missing'      => max(0, $thresholds['min_likes'] - $stats['total_likes']),
        ];
    }
}
