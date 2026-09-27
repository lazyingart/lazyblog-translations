# Low-overhead translation and optional fallback

Version 0.4.18 uses `deepseek-flash` (DeepSeek V4.1 Flash) for direct DeepSeek
translation. Select **Settings → LazyBlog Translations → DeepSeek direct API**
and enter a server-side DeepSeek API key. No local service is needed for the
primary provider. Existing saved translations remain unchanged.

## Token and request budget

- Send only the source title, content and excerpt, plus language codes and a
  short fixed instruction. No chat history, project files or tools are sent.
- Disable DeepSeek thinking explicitly; the vendor otherwise enables it by
  default. Keep the output cap at 8,192 tokens. This is a ceiling, not a charge
  for unused tokens. Do not summarize an article to fit the limit.
- Saved translations return immediately without another provider request.
- Never save output cut off by the token limit, filtered output, malformed JSON
  or non-string translation fields. For oversized posts, select the background
  provider or use a reviewed Markdown translation workflow.
- Record provider-reported prompt, completion and total token counts in job
  metadata. Credentials and source text are not included in those counters.

## Concurrent readers and failures

Each post/language has an atomic MySQL advisory lock around job creation or
direct generation. Transients supply a short-lived UI hint, but are not the
authority for excluding duplicate workers. Advisory locks disappear when the
database connection closes; a crashed worker's transient expires after two
minutes. Independent languages can generate concurrently.

Completed results and job updates use a separate brief per-post metadata lock.
They reload uncached metadata before merging, so simultaneous languages do not
overwrite each other's records. Manual translation PUT/DELETE uses the same
merge lock. Literal backslashes in math and code survive WordPress unslashing.

Requires database support for multiple named advisory locks per connection
(MySQL 5.7.5+ or MariaDB 10.0.2+). Tested on MySQL 8 / PHP 8.3. A failed lock
acquisition never starts a provider request. Existing signed-request checks and
rate limits remain in place. Failed direct requests have a 60-second retry
cooldown. Authentication/configuration errors must be corrected, not retried in
an unbounded loop.

## Codex fallback (opt-in)

The **Optional Codex fallback** checkbox is off by default. Enable it only after
the configured local API endpoint, bearer token and Codex model have passed a
real translation job test. After a temporary network error, HTTP 408/429 or
500/502/503/504, one background Codex job may be started. Subsequent clicks poll
that same job. Failed fallback jobs also get a cooldown. The plugin does not
cascade through models, loop between providers, or silently fallback on bad
credentials or truncated output. Changing provider in settings remains available.

Codex 0.125.0 was unable to parse a newer model catalog's `max` reasoning value.
An account also rejected `gpt-5.4` as unsupported. This is not evidence of a
universal API deprecation. The service must use its maintained CLI binary and a
model actually available to its account; `gpt-5.5` / `low` was verified for this
release. Interactive login success alone does not test a background worker's
PATH, chosen model, schema output or authentication environment.

With the LazyBlog launcher, set `LAZYBLOG_CODEX_BIN_DIR` and
`LAZYBLOG_WEBAPP_MODEL` in the service's private `.env`. The launcher reads them
before constructing its command. Restart only that service's tmux session after
checking for active jobs. Keep the bearer token and provider keys private.

## Limits and validation

The direct request currently runs inside the WordPress request, with a 90-second
HTTP timeout. It is not a durable background queue. Closing the browser or a
hosting proxy timeout may require a later retry; a successfully saved result
will still be reused. The Codex path remains the durable background option.
There is no automatic invalidation of existing human-reviewed translations on
source edits; update them explicitly through the Markdown workflow.

Run without credentials or network:

```sh
php -l lazyblog-translations.php
php tests/provider-resilience-test.php
php tests/translation-slash-storage-test.php
php tests/canonical-hreflang-test.php
node --test tests/utm-bridge.test.mjs
```

Tests cover payload size choices, non-thinking mode, incomplete/schema-invalid
responses, cache hits, lock contention, metadata merges, math preservation,
failure cooldowns and one-job fallback polling. They do not replace live
database and provider checks.

Vendor references:

- [DeepSeek V4.1 Flash and its API name](https://api-docs.deepseek.com/news/news260910/)
- [DeepSeek thinking-mode controls](https://api-docs.deepseek.com/guides/thinking_mode/)
- [Codex non-interactive execution](https://learn.chatgpt.com/docs/non-interactive-mode)
