<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_Spam_Filter
{
    /**
     * @return array{is_spam: bool, score: float}
     */
    public function analyze(string $text): array
    {
        $heuristic = $this->analyze_link_ratio($text);
        if ($heuristic['is_spam']) {
            return $heuristic;
        }

        if (!$this->is_ai_enabled()) {
            return ['is_spam' => false, 'score' => $heuristic['score']];
        }

        $endpoint = defined('CROWDBOOK_OLLAMA_ENDPOINT')
            ? (string) CROWDBOOK_OLLAMA_ENDPOINT
            : 'http://localhost:11434/api/generate';
        $model = defined('CROWDBOOK_OLLAMA_MODEL')
            ? (string) CROWDBOOK_OLLAMA_MODEL
            : 'phi3:mini';
        $timeout = defined('CROWDBOOK_OLLAMA_TIMEOUT')
            ? max(1, (int) CROWDBOOK_OLLAMA_TIMEOUT)
            : 15;

        $prompt = "You are a spam filter. Analyze this text and respond with ONLY 'spam' or 'ok'.\n"
            . "Spam: advertising, viagra, casino, penis enlargement, SEO links, gibberish, unrelated commercial content.\n"
            . "Ok: personal stories, spiritual content, nature, community, creative writing, life experiences.\n\n"
            . 'Text: ' . mb_substr($text, 0, 500);

        $response = wp_remote_post($endpoint, [
            'body' => wp_json_encode([
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
            ]),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => $timeout,
        ]);

        if (is_wp_error($response)) {
            return ['is_spam' => false, 'score' => $heuristic['score']];
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $result = strtolower(trim((string) ($body['response'] ?? 'ok')));
        $is_spam = $result === 'spam';
        $score = $is_spam ? 1.0 : max($heuristic['score'], 0.0);

        return ['is_spam' => $is_spam, 'score' => $score];
    }

    private function is_ai_enabled(): bool
    {
        return defined('CROWDBOOK_ENABLE_SPAM_FILTER') && (bool) CROWDBOOK_ENABLE_SPAM_FILTER;
    }

    /**
     * Simple local anti-spam: blocks chapters with too high link density.
     *
     * @return array{is_spam: bool, score: float}
     */
    private function analyze_link_ratio(string $text): array
    {
        $max_ratio = defined('CROWDBOOK_LINK_SPAM_MAX_RATIO')
            ? (float) CROWDBOOK_LINK_SPAM_MAX_RATIO
            : 0.12;
        $min_links = defined('CROWDBOOK_LINK_SPAM_MIN_LINKS')
            ? max(1, (int) CROWDBOOK_LINK_SPAM_MIN_LINKS)
            : 5;

        $word_count = str_word_count(wp_strip_all_tags($text));
        if ($word_count <= 0) {
            return ['is_spam' => false, 'score' => 0.0];
        }

        $markdown_web_links = 0;
        $inline_web_links = 0;
        $local_image_links = 0;
        $external_image_links = 0;

        if (preg_match_all('/(?<!!)\[[^\]]+\]\((https?:\/\/[^)\s]+)\)/iu', $text, $web_matches) && isset($web_matches[1])) {
            $markdown_web_links = count($web_matches[1]);
        }

        if (preg_match_all('/!\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/iu', $text, $image_matches) && isset($image_matches[1])) {
            foreach ($image_matches[1] as $image_url) {
                if ($this->is_local_url((string) $image_url)) {
                    $local_image_links++;
                } else {
                    $external_image_links++;
                }
            }
        }

        // Bare links are counted only if they are not inside markdown link syntax.
        if (preg_match_all('/(?:^|[\s>])(https?:\/\/[^\s<>"\]\)]+)/iu', $text, $inline_matches) && isset($inline_matches[1])) {
            $inline_web_links = count($inline_matches[1]);
        }

        $web_link_count = $markdown_web_links + $inline_web_links + $external_image_links;

        $ratio = $web_link_count / max(1, $word_count);
        $score = min(1.0, $ratio / max(0.0001, $max_ratio));
        $is_spam = $web_link_count >= $min_links && $ratio > $max_ratio;

        return [
            'is_spam' => $is_spam,
            'score' => round($score, 4),
        ];
    }

    private function is_local_url(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        if (!preg_match('#^https?://#i', $url)) {
            return false;
        }

        $home_host = parse_url(home_url('/'), PHP_URL_HOST);
        $link_host = parse_url($url, PHP_URL_HOST);

        if (!is_string($home_host) || !is_string($link_host) || $home_host === '' || $link_host === '') {
            return false;
        }

        return strtolower($home_host) === strtolower($link_host);
    }
}
