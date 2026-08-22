=== AI Provider for LiteLLM ===
Contributors:
Tags: ai, litellm, ollama, ai-client
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Registers an AI Client provider that talks to a LiteLLM gateway (e.g. backed by Ollama) using the OpenAI-compatible wire protocol.

== Description ==

This plugin registers a `litellm` provider with the WordPress PHP AI Client SDK, so that the official AI plugin (and any other AI-Client-based code) can generate text through a self-hosted [LiteLLM](https://www.litellm.ai/) gateway — for example one proxying local Ollama models.

Once activated, the provider is automatically picked up by WordPress core's Connectors API (WP 7.0+): it appears on **Settings → Connectors** as "LiteLLM (Ollama)", where you can enter your LiteLLM API key. The gateway's base URL and a default model are configured separately on **Settings → LiteLLM Provider**.

= Configuration =

* **API Key** — set on **Settings → Connectors**, or via the `LITELLM_API_KEY` environment variable / PHP constant.
* **API Base URL** — set on **Settings → LiteLLM Provider**, or via the `LITELLM_API_BASE` environment variable. Defaults to `http://localhost:4000/v1`.
* **Request Timeout** — set on **Settings → LiteLLM Provider**, or via the `LITELLM_REQUEST_TIMEOUT` environment variable (seconds). Defaults to 30. Applies to text generation requests only; increase it if you're using slower local models that take longer than 30 seconds to respond. The plugin also raises PHP's own `max_execution_time` to match before sending the request (see below), since that would otherwise cut a request short regardless of this setting, and raises WordPress core's site-wide default AI request timeout to at least this value (see below), since core's own built-in AI features (Content Resizing, Alt Text Generation, etc.) otherwise ignore this setting entirely.

Precedence for all three is: WordPress option, then environment variable / constant, then default.

= Known limitations (v1) =

* **No streaming.** The SDK's OpenAI-compatible text generation base class sends and parses one full response per request; there is no chunked/SSE code path to hook into.
* **No image generation/description, embeddings, or other modalities yet.** Only chat-style text generation is implemented in this version.
* **No custom retry/backoff.** Requests use the configured timeout (see above) but a failed or timed-out request is not automatically retried.
* **PHP's `max_execution_time` is only raised on a best-effort basis.** The plugin calls `set_time_limit()` before sending a text generation request, matching it to the configured Request Timeout. This does nothing if your host has disabled `set_time_limit` (some do, via `disable_functions`), and it cannot raise separate hard limits some environments impose outside PHP itself -- e.g. PHP-FPM's `request_terminate_timeout`, or a web server/reverse-proxy read timeout. If generation still fails around a suspiciously round number of seconds (30s and 60s are common defaults) after increasing the Request Timeout setting, check those first.
* **Raising WordPress core's default AI request timeout is site-wide, not LiteLLM-specific.** WP core's built-in AI features (Content Resizing, Alt Text Generation, etc.) apply their own 30-second default via the `wp_ai_client_default_request_timeout` filter, which overrides any provider's own configured timeout and isn't told which provider will end up handling the request. This plugin raises that filter's value to match the Request Timeout setting -- but only ever raises it, never lowers it -- so if you also have a fast cloud provider (OpenAI, Anthropic, etc.) configured, its requests through core Abilities will wait just as long as LiteLLM's before core considers them timed out.
* **Model discovery requests (`GET /v1/models`) have no explicit timeout**, unlike text generation — they use WordPress core's own default HTTP timeout (5 seconds, filterable via the `http_request_timeout` core filter), which can be tight for a slow or cold-starting LiteLLM instance.
* Because LiteLLM can proxy arbitrary, differently-named underlying models, this plugin cannot infer each model's true capabilities from its ID the way a single-vendor provider can. Every model returned by `GET /v1/models` is assumed to support text generation, unless its ID contains "embed", in which case it's assumed to be an embedding model instead.

== Installation ==

1. Install and activate this plugin alongside the official [AI](https://github.com/WordPress/ai) plugin (or on WordPress 7.0+, where the AI Client SDK ships in core).
2. Go to **Settings → Connectors** and enter your LiteLLM API key for "LiteLLM (Ollama)".
3. Go to **Settings → LiteLLM Provider** and set your LiteLLM gateway's base URL and a default model.

== Changelog ==

= 0.1.3 =
* Raise WordPress core's `wp_ai_client_default_request_timeout` filter to match the configured Request Timeout. Previously, any generation triggered through a built-in WP AI feature (Content Resizing, Alt Text Generation, etc.) silently ignored this plugin's Request Timeout setting entirely, because WP core's own prompt-builder wrapper applies a hardcoded 30-second default that takes precedence over a provider's own configured timeout.

= 0.1.2 =
* Raise PHP's `max_execution_time` (via `set_time_limit()`) to match the configured Request Timeout before sending a text generation request. Previously, a slow model response could be killed by PHP's own default execution time limit (commonly 30 seconds) well before a longer configured Request Timeout was ever reached.

= 0.1.1 =
* Make the text generation request timeout configurable (Settings → LiteLLM Provider, or `LITELLM_REQUEST_TIMEOUT`), default unchanged at 30 seconds.
* Remove the connect-timeout setting: confirmed it had no effect under WordPress's own HTTP client, which only supports a single overall request timeout.

= 0.1.0 =
* Initial release: text generation, model discovery/availability, and base-URL/default-model settings.
