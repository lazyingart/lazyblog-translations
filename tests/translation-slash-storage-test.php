<?php

define('ABSPATH', __DIR__ . '/');

class WP_REST_Request implements ArrayAccess
{
    private array $route;
    private array $params;

    public function __construct(array $route, array $params)
    {
        $this->route = $route;
        $this->params = $params;
    }

    public function get_json_params(): array { return $this->params; }
    public function get_params(): array { return $this->params; }
    public function offsetExists($offset): bool { return isset($this->route[$offset]); }
    public function offsetGet($offset) { return $this->route[$offset] ?? null; }
    public function offsetSet($offset, $value): void { $this->route[$offset] = $value; }
    public function offsetUnset($offset): void { unset($this->route[$offset]); }
}

class WP_REST_Response
{
    public array $data;
    public function __construct(array $data) { $this->data = $data; }
}

class WP_Error {}

function add_action(...$args): void {}
function add_filter(...$args): void {}
function add_shortcode(...$args): void {}
function register_activation_hook(...$args): void {}
function register_deactivation_hook(...$args): void {}
function apply_filters($hook, $value) { return $value; }
function get_option($name) { return null; }
function get_post_meta($post_id, $key, $single = false)
{
    return $GLOBALS['lazyblog_test_meta'][$key] ?? '';
}
function update_post_meta($post_id, $key, $value): bool
{
    $GLOBALS['lazyblog_test_meta'][$key] = stripslashes_deep($value);
    return true;
}
function stripslashes_deep($value)
{
    if (is_array($value)) {
        return array_map('stripslashes_deep', $value);
    }
    return is_string($value) ? stripslashes($value) : $value;
}
function wp_slash($value)
{
    if (is_array($value)) {
        return array_map('wp_slash', $value);
    }
    return is_string($value) ? addslashes($value) : $value;
}
function sanitize_text_field(string $value): string { return $value; }
function wp_kses_post(string $value): string { return $value; }
function current_time($type, $gmt = false): string { return '2026-09-10 00:00:00'; }
function wp_cache_flush(): bool { return true; }
function wp_cache_delete(...$args): bool { return true; }
$GLOBALS['wpdb'] = new class {
    public string $prefix = 'wp_';
    public function prepare($sql, ...$args) { return $sql; }
    public function get_var($sql) { return '1'; }
};

require dirname(__DIR__) . '/lazyblog-translations.php';

$GLOBALS['lazyblog_test_meta'] = ['_lazyblog_translations' => []];
$content = "<pre><code>postiz posts:create \\\n  -c 'reviewed'</code></pre>";
$request = new WP_REST_Request(
    ['id' => 3772, 'lang' => 'ja'],
    ['title' => 'Test', 'content' => $content, 'excerpt' => '']
);

LazyBlog_Translations::instance()->rest_put_translation($request);
$stored = $GLOBALS['lazyblog_test_meta']['_lazyblog_translations']['ja']['content'] ?? '';

if ($stored !== $content) {
    fwrite(STDERR, "Translation storage changed literal backslashes.\n");
    exit(1);
}

echo "translation slash storage check passed\n";
