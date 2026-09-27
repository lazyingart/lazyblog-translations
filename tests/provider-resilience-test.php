<?php
// Standalone regression tests; no WordPress, credentials or network required.
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
class WP_Post {
    public int $ID = 42;
    public string $post_content = '<p>你好 <a href="https://example.org/a">Link</a> \\(x^2\\)</p>';
    public string $post_excerpt = '';
}
class WP_REST_Request extends ArrayObject {}
class WP_REST_Response {
    public array $data;
    public int $status;
    public function __construct($data, $status = 200) { $this->data = $data; $this->status = $status; }
}
class WP_Error {
    private $code; private $message; private $data;
    public function __construct($code, $message, $data = []) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
function add_action(...$a) {}
function add_filter(...$a) {}
function add_shortcode(...$a) {}
function register_activation_hook(...$a) {}
function register_deactivation_hook(...$a) {}
function apply_filters($hook, $value) { return $value; }
function __($text, $domain = '') { return $text; }
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function get_post($id) { return new WP_Post(); }
function get_the_title($post) { return 'Source title'; }
function get_permalink($id) { return 'https://example.org/post'; }
function home_url($path) { return 'https://example.org' . $path; }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['meta'][$key] ?? ''; }
function wp_slash($v) { return is_array($v) ? array_map('wp_slash', $v) : (is_string($v) ? addslashes($v) : $v); }
function unslash($v) { return is_array($v) ? array_map('unslash', $v) : (is_string($v) ? stripslashes($v) : $v); }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$key] = unslash($value); return true; }
function sanitize_text_field($v) { return $v; }
function sanitize_key($v) { return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $v)); }
function wp_kses_post($v) { return $v; }
function current_time(...$a) { return gmdate('Y-m-d H:i:s'); }
function wp_cache_flush() { return true; }
function wp_cache_delete(...$a) { $GLOBALS['cache_deletes']++; return true; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
function delete_transient($key) { unset($GLOBALS['transients'][$key]); }
function wp_generate_uuid4() { return 'test-uuid'; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code($r) { return $r['status']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function wp_remote_post($url, $args) { $GLOBALS['http'][] = [$url, $args]; return array_shift($GLOBALS['responses']); }
function wp_remote_get($url, $args) { return wp_remote_post($url, $args); }
function add_query_arg($key, $value, $url) { return $url . '?' . $key . '=' . $value; }
$GLOBALS['wpdb'] = new class {
    public string $prefix = 'test_';
    public function prepare($sql, ...$args) { return [$sql, $args]; }
    public function get_var($query) {
        if (strpos($query[0], 'GET_LOCK') !== false && !empty($GLOBALS['deny_lock'])) { return '0'; }
        return '1';
    }
};
function reset_state() {
    $GLOBALS['options'] = ['lazyblog_translation_provider' => 'deepseek', 'lazyblog_translation_deepseek_api_key' => 'test-key'];
    $GLOBALS['meta'] = ['_lazyblog_source_language' => 'en'];
    $GLOBALS['transients'] = $GLOBALS['http'] = $GLOBALS['responses'] = [];
    $GLOBALS['cache_deletes'] = 0; $GLOBALS['deny_lock'] = false;
}
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function invoke($method, ...$args) {
    $m = new ReflectionMethod(LazyBlog_Translations::class, $method); $m->setAccessible(true);
    return $m->invoke(LazyBlog_Translations::instance(), ...$args);
}
function response($body, $status = 200) { return ['status' => $status, 'body' => json_encode($body)]; }
function translation_response($finish = 'stop', $fields = null) {
    return response(['choices' => [['finish_reason' => $finish, 'message' => ['content' => json_encode($fields ?? ['title' => '中文标题', 'content' => '<p>中文 \\(x^2\\)</p>', 'excerpt' => ''])]]]]);
}
function ensure_language($lang = 'zh') {
    return LazyBlog_Translations::instance()->rest_ensure_translation(new WP_REST_Request(['id' => 42, 'lang' => $lang]));
}
require dirname(__DIR__) . '/lazyblog-translations.php';
reset_state();
$payload = invoke('direct_provider_payload', 42, 'zh', 'deepseek');
check(invoke('translation_job_is_stale', ['status' => 'running', 'started_at' => gmdate('Y-m-d H:i:s', time() - 1300), 'updated_at' => gmdate('Y-m-d H:i:s')]), 'Polling must not revive an orphaned job');
check($payload['model'] === 'deepseek-flash', 'Use current Flash API ID');
check($payload['thinking']['type'] === 'disabled', 'No paid reasoning for routine translation');
check($payload['max_tokens'] === 8192, 'Bounded output budget');
check(strpos($payload['messages'][1]['content'], 'post_url') === false, 'No unrelated request metadata');
check(strpos($payload['messages'][1]['content'], '你好') !== false, 'Keep Unicode unescaped');
check(!isset(invoke('direct_provider_payload', 42, 'zh', 'openai')['thinking']), 'Do not send DeepSeek-only options to OpenAI');
foreach (['length', 'content_filter', 'tool_calls'] as $finish) {
    check(is_wp_error(invoke('decode_direct_provider_response', translation_response($finish))), 'Reject partial output');
}
check(is_wp_error(invoke('decode_direct_provider_response', translation_response('stop', ['title' => [], 'content' => 'body', 'excerpt' => '']))), 'Reject invalid field types');
$GLOBALS['responses'][] = translation_response();
check(ensure_language()->data['status'] === 'ready', 'Direct translation succeeds');
check(ensure_language()->data['status'] === 'ready' && count($GLOBALS['http']) === 1, 'Saved translations cost zero further requests');
check(strpos($GLOBALS['meta']['_lazyblog_translations']['zh']['content'], '\\(') !== false, 'Preserve math backslashes');
$GLOBALS['responses'][] = translation_response();
ensure_language('ja');
check(isset($GLOBALS['meta']['_lazyblog_translations']['zh'], $GLOBALS['meta']['_lazyblog_translations']['ja']), 'Keep other languages when merging');
check($GLOBALS['cache_deletes'] > 0, 'Reload metadata under lock');
reset_state(); $GLOBALS['deny_lock'] = true;
check(ensure_language()->data['status'] === 'queued' && count($GLOBALS['http']) === 0, 'Contended lock must not bill');
reset_state(); $GLOBALS['responses'][] = translation_response('length');
check(ensure_language()->status === 502, 'Incomplete result is an error');
check(empty($GLOBALS['meta']['_lazyblog_translations']), 'Incomplete output is not stored');
check(ensure_language()->status === 429 && count($GLOBALS['http']) === 1, 'Cooldown prevents paid retry storms');
reset_state();
$GLOBALS['options'] += ['lazyblog_translation_codex_fallback' => true, 'lazyblog_translation_api_endpoint' => 'http://localhost/api/translate/jobs', 'lazyblog_translation_api_token' => 'test-token'];
check(!invoke('should_fallback_to_codex', new WP_Error('lazyblog_direct_provider_failed', 'bad key', ['status' => 401])), 'No fallback on bad credentials');
check(!invoke('should_fallback_to_codex', new WP_Error('lazyblog_direct_provider_incomplete', 'truncated', ['status' => 502])), 'No expensive retry of truncated output');
$GLOBALS['responses'][] = response(['error' => ['message' => 'Unavailable']], 503);
$GLOBALS['responses'][] = response(['ok' => true, 'job' => ['id' => 'fallback-one', 'status' => 'queued']]);
check(ensure_language()->data['status'] === 'queued', 'Enqueue one optional fallback');
$GLOBALS['responses'][] = response(['ok' => true, 'job' => ['id' => 'fallback-one', 'status' => 'running']]);
check(ensure_language()->data['status'] === 'running', 'Poll existing fallback instead of generating again');
check(strpos($GLOBALS['http'][2][0], '/translate/job?id=') !== false, 'Poll correct job route');
$GLOBALS['responses'][] = response(['ok' => true, 'job' => ['id' => 'fallback-one', 'status' => 'succeeded'], 'output' => ['title' => '译文', 'content' => '内容', 'excerpt' => '']]);
check(ensure_language()->data['status'] === 'ready', 'Save completed fallback');
$count = count($GLOBALS['http']); ensure_language();
check(count($GLOBALS['http']) === $count, 'Fallback output is cached like direct output');
reset_state(); $GLOBALS['responses'][] = response(['error' => ['message' => 'Unavailable']], 503);
ensure_language(); check(count($GLOBALS['http']) === 1, 'Fallback is opt-in');
reset_state();
$key = 'lazyblog_translate_start_lang_' . md5('unknown|42|zh');
$GLOBALS['transients'][$key] = 4;
check(ensure_language()->status === 429 && count($GLOBALS['http']) === 0, 'Rate-limit real starts before billing');
check(empty($GLOBALS['transients']['lazyblog_translate_lock_42_' . md5('zh')]), 'Release lock after rate limiting');
echo "provider resilience checks passed\n";
