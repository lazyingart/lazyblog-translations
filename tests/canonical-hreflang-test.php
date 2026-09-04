<?php

define('ABSPATH', __DIR__ . '/');

class WP_Post
{
    public int $ID = 3791;
    public string $post_status = 'publish';
    public string $post_type = 'post';
}

class Other_WP_Post extends WP_Post
{
    public int $ID = 42;
}

function add_action(...$args): void {}
function add_filter(...$args): void {}
function apply_filters($hook, $value) { return $value; }
function add_shortcode(...$args): void {}
function register_activation_hook(...$args): void {}
function register_deactivation_hook(...$args): void {}
function is_admin(): bool { return false; }
function is_singular($type = ''): bool { return $type === 'post'; }
function get_queried_object(): WP_Post { return $GLOBALS['lazyblog_test_post']; }
function get_queried_object_id(): int { return 3791; }
function get_query_var($name) { return ''; }
function get_option($name) { return null; }
function get_permalink($post): string
{
    return 'https://blog.example/html/books/3791/ocr-guide.html';
}
function home_url(string $path): string
{
    return 'https://blog.example/' . ltrim($path, '/');
}
function get_post_meta($post_id, $key, $single = false)
{
    if ($key === '_lazyblog_source_language') {
        return 'en';
    }
    if ($key === '_lazyblog_translations') {
        return [
            'zh' => ['title' => '中文', 'content' => '内容', 'excerpt' => '', 'updated_at' => ''],
            'ja' => ['title' => '日本語', 'content' => '内容', 'excerpt' => '', 'updated_at' => ''],
            'ko' => ['title' => '', 'content' => '', 'excerpt' => '', 'updated_at' => ''],
        ];
    }
    return '';
}
function esc_attr(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_url(string $value): string { return $value; }

function assert_same(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . $expected . "\nActual: " . $actual . "\n");
        exit(1);
    }
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . "\nMissing: " . $needle . "\n");
        exit(1);
    }
}

require dirname(__DIR__) . '/lazyblog-translations.php';

$GLOBALS['lazyblog_test_post'] = new WP_Post();
$plugin = LazyBlog_Translations::instance();
$property = new ReflectionProperty(LazyBlog_Translations::class, 'current_language');
$property->setAccessible(true);
$property->setValue($plugin, 'zh');

assert_same(
    'https://blog.example/zh/html/books/3791/ocr-guide.html',
    $plugin->filter_canonical_url('https://blog.example/html/books/3791/ocr-guide.html', $GLOBALS['lazyblog_test_post']),
    'Translated routes must be self-canonical.'
);

$property->setValue($plugin, 'en');
assert_same(
    'https://blog.example/html/books/3791/ocr-guide.html',
    $plugin->filter_canonical_url('fallback', $GLOBALS['lazyblog_test_post']),
    'The source route must retain the unprefixed canonical URL.'
);
assert_same(
    'https://elsewhere.example/post',
    $plugin->filter_canonical_url('https://elsewhere.example/post', new Other_WP_Post()),
    'Canonical calls for posts outside the queried route must remain unchanged.'
);

ob_start();
$plugin->render_language_alternates();
$alternates = (string) ob_get_clean();

assert_contains('hreflang="en" href="https://blog.example/html/books/3791/ocr-guide.html"', $alternates, 'Source hreflang is missing.');
assert_contains('hreflang="zh-Hans" href="https://blog.example/zh/html/books/3791/ocr-guide.html"', $alternates, 'Chinese hreflang is missing.');
assert_contains('hreflang="ja" href="https://blog.example/ja/html/books/3791/ocr-guide.html"', $alternates, 'Japanese hreflang is missing.');
assert_contains('hreflang="x-default" href="https://blog.example/html/books/3791/ocr-guide.html"', $alternates, 'x-default must resolve to the source edition.');

if (strpos($alternates, 'hreflang="ko"') !== false) {
    fwrite(STDERR, "Empty translations must not be advertised.\n");
    exit(1);
}

echo "canonical and hreflang checks passed\n";
