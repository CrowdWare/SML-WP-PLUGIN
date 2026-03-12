<?php

if (!defined('ABSPATH')) {
    exit;
}

class CrowdBook_SML_Renderer
{
    public function build_sml(string $title, string $author, string $path_label, string $markdown): string
    {
        $safe_title = str_replace('"', '\\"', $title);
        $safe_author = str_replace('"', '\\"', $author);
        $safe_path = str_replace('"', '\\"', $path_label);
        $safe_markdown = str_replace('"', '\\"', $markdown);

        return "Page {\n"
            . "  template: \"tweak\"\n"
            . "  Chapter {\n"
            . "    book: \"choose-your-incarnation\"\n"
            . "    title: \"{$safe_title}\"\n"
            . "    author: \"{$safe_author}\"\n"
            . "    path: \"{$safe_path}\"\n"
            . "    Markdown {\n"
            . "      text: \"{$safe_markdown}\"\n"
            . "    }\n"
            . "    Navigation {\n"
            . "      back: \"Wähle deinen Weg\"\n"
            . "      back_url: \"/choose-your-incarnation/\"\n"
            . "    }\n"
            . "  }\n"
            . "}";
    }

    public function render_markdown_html(string $markdown): string
    {
        $source = str_replace(["\r\n", "\r"], "\n", (string) $markdown);
        $source = wp_kses($source, []);
        $escaped = esc_html($source);

        // Image markdown syntax: ![alt](url)
        $escaped = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"&quot;([^&]*)&quot;")?\)/u', function (array $m): string {
            $alt = sanitize_text_field(html_entity_decode((string) ($m[1] ?? ''), ENT_QUOTES));
            $url = html_entity_decode((string) ($m[2] ?? ''), ENT_QUOTES);
            if ($url === '' || !$this->is_local_url($url)) {
                return $m[0];
            }

            return '<img class="crowdbook-inline-image" src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" loading="lazy" />';
        }, $escaped);

        // Standard markdown links: [text](url)
        $escaped = preg_replace_callback('/(?<!!)\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/u', function (array $m): string {
            $label = sanitize_text_field(html_entity_decode((string) ($m[1] ?? ''), ENT_QUOTES));
            $url = html_entity_decode((string) ($m[2] ?? ''), ENT_QUOTES);
            if ($url === '') {
                return $m[0];
            }

            return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener nofollow">' . esc_html($label) . '</a>';
        }, $escaped);

        // Basic markdown emphasis.
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped ?? '');
        $escaped = preg_replace('/(^|[\s\(\[])\*(?!\*)([^*\n]+)\*(?!\*)/u', '$1<em>$2</em>', $escaped ?? '');

        // Headings (#, ##, ...).
        $lines = explode("\n", (string) $escaped);
        foreach ($lines as $idx => $line) {
            if (preg_match('/^\s*(#{1,6})\s+(.+)\s*$/u', $line, $m)) {
                $level = strlen((string) $m[1]);
                $text = trim((string) $m[2]);
                $lines[$idx] = '<h' . $level . '>' . $text . '</h' . $level . '>';
            }
        }
        $escaped = implode("\n", $lines);

        // Plain URLs/emails become clickable (e.g. artanidos@gmail.com).
        $escaped = make_clickable($escaped);

        return wp_kses_post(wpautop($escaped));
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

    public function write_static_html(string $slug, string $html): ?string
    {
        $uploads = wp_upload_dir();
        if (!is_array($uploads) || empty($uploads['basedir'])) {
            return null;
        }

        $dir = trailingslashit($uploads['basedir']) . 'crowdbook';
        if (!wp_mkdir_p($dir)) {
            return null;
        }

        $file = trailingslashit($dir) . sanitize_file_name($slug) . '.html';
        $written = file_put_contents($file, $html);

        return $written === false ? null : $file;
    }
}
