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

        // ── Step 1: Extract code blocks before any escaping ─────────────────
        // This ensures code content is displayed verbatim and cannot inject HTML.
        $placeholders = [];
        $counter = 0;

        // Fenced code blocks: ```lang\n...\n```
        $source = preg_replace_callback(
            '/^```([^\n]*)\n(.*?)^```[ \t]*$/msu',
            function (array $m) use (&$placeholders, &$counter): string {
                $lang  = sanitize_html_class(trim((string) $m[1]));
                $code  = esc_html((string) $m[2]);
                $class = $lang !== '' ? ' class="language-' . $lang . '"' : '';
                $html  = '<pre class="crowdbook-code-block"><code' . $class . '>' . $code . '</code></pre>';
                $key   = "\x02CB_{$counter}\x03";
                $placeholders[$key] = $html;
                $counter++;
                return "\n" . $key . "\n";
            },
            $source
        ) ?? $source;

        // Inline code: `...`
        $source = preg_replace_callback(
            '/`([^`\n]+)`/u',
            function (array $m) use (&$placeholders, &$counter): string {
                $code = esc_html((string) $m[1]);
                $html = '<code class="crowdbook-inline-code">' . $code . '</code>';
                $key  = "\x02CB_{$counter}\x03";
                $placeholders[$key] = $html;
                $counter++;
                return $key;
            },
            $source
        ) ?? $source;

        // ── Step 2: Escape remaining source ─────────────────────────────────
        // Inline HTML like <component/> is shown literally, not rendered.
        // Placeholders contain control chars (\x02/\x03) that survive esc_html.
        $escaped = esc_html($source);

        // ── Step 3: Markdown syntax ──────────────────────────────────────────

        // Image markdown syntax: ![alt](url)
        $escaped = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"&quot;([^&]*)&quot;")?\)/u', function (array $m): string {
            $alt = sanitize_text_field(html_entity_decode((string) ($m[1] ?? ''), ENT_QUOTES));
            $url = html_entity_decode((string) ($m[2] ?? ''), ENT_QUOTES);
            if ($url === '' || !$this->is_local_url($url)) {
                return $m[0];
            }

            return '<img class="crowdbook-inline-image" src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" loading="lazy" />';
        }, $escaped) ?? $escaped;

        // Standard markdown links: [text](url)
        $escaped = preg_replace_callback('/(?<!!)\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/u', function (array $m): string {
            $label = sanitize_text_field(html_entity_decode((string) ($m[1] ?? ''), ENT_QUOTES));
            $url   = html_entity_decode((string) ($m[2] ?? ''), ENT_QUOTES);
            if ($url === '') {
                return $m[0];
            }

            return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener nofollow">' . esc_html($label) . '</a>';
        }, $escaped) ?? $escaped;

        // Horizontal rules: --- or *** or ___ on their own line.
        $escaped = preg_replace('/^[ \t]*(?:(?:-[ \t]*){3,}|(?:\*[ \t]*){3,}|(?:_[ \t]*){3,})$/mu', '<hr/>', $escaped ?? '');

        // Basic markdown emphasis.
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped ?? '');
        $escaped = preg_replace('/(^|[\s\(\[])\*(?!\*)([^*\n]+)\*(?!\*)/u', '$1<em>$2</em>', $escaped ?? '');

        // Headings (#, ##, ...).
        $lines = explode("\n", (string) $escaped);
        foreach ($lines as $idx => $line) {
            if (preg_match('/^\s*(#{1,6})\s+(.+)\s*$/u', $line, $m)) {
                $level       = strlen((string) $m[1]);
                $text        = trim((string) $m[2]);
                $lines[$idx] = '<h' . $level . '>' . $text . '</h' . $level . '>';
            }
        }
        $escaped = implode("\n", $lines);

        // Plain URLs/emails become clickable.
        $escaped = make_clickable($escaped);

        // ── Step 4: Restore placeholders after wpautop/kses ─────────────────
        // wpautop wraps block-level placeholders in <p>; we strip that wrapper.
        $result = wp_kses_post(wpautop((string) $escaped));

        $result = preg_replace_callback(
            '/<p>\s*\x02CB_(\d+)\x03\s*<\/p>/u',
            function (array $m) use ($placeholders): string {
                return $placeholders["\x02CB_{$m[1]}\x03"] ?? '';
            },
            (string) $result
        ) ?? $result;

        // Restore inline code placeholders still inside paragraphs.
        return str_replace(array_keys($placeholders), array_values($placeholders), (string) $result);
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
