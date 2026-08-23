=== AI Provider for LiteLLM ===
Contributors:
Tags: ai, litellm, ollama, ai-client
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Registers an AI Client provider that talks to a LiteLLM gateway (e.g. backed by Ollama) using the OpenAI-compatible wire protocol.

== Description ==

This plugin registers a `litellm` provider with the WordPress PHP AI Client SDK, so that the official AI plugin (and any other AI-Client-based code) can generate text through a self-hosted [LiteLLM](https://www.litellm.ai/) gateway — for example one proxying local Ollama models.

Once activated, the provider is automatically picked up by WordPress core's Connectors API (WP 7.0+): it appears on **Settings → Connectors** as "LiteLLM (Ollama)", where you can enter your LiteLLM API key. The gateway's base URL, a default model, and vision-capable models are configured separately on **Settings → LiteLLM Provider**.

Text generation supports image input (i.e. describing an image in text, such as generating alt text) for any model you mark as vision-capable — see "Image description generation" below. It also supports structured JSON output for any model you mark as structured-output-capable, required by several of WordPress core's built-in AI features (Content Classification/tag suggestions, Type Ahead, and others that request a specific response schema) — see "Structured JSON output" below.

= Configuration =

* **API Key** — set on **Settings → Connectors**, or via the `LITELLM_API_KEY` environment variable / PHP constant.
* **API Base URL** — set on **Settings → LiteLLM Provider**, or via the `LITELLM_API_BASE` environment variable. Defaults to `http://localhost:4000/v1`.
* **Vision-Capable Models** — set on **Settings → LiteLLM Provider**: a list of model IDs (one per line, or comma-separated) that accept image input.
* **Structured Output-Capable Models** — set on **Settings → LiteLLM Provider**: a list of model IDs that reliably return valid, schema-conformant JSON.
* **Request Timeout** — set on **Settings → LiteLLM Provider**, or via the `LITELLM_REQUEST_TIMEOUT` environment variable (seconds). Defaults to 30. Applies to text generation requests only; increase it if you're using slower local models that take longer than 30 seconds to respond. The plugin also raises PHP's own `max_execution_time` to match before sending the request (see below), since that would otherwise cut a request short regardless of this setting, and raises WordPress core's site-wide default AI request timeout to at least this value (see below), since core's own built-in AI features (Content Resizing, Alt Text Generation, etc.) otherwise ignore this setting entirely.

Precedence for the API key, base URL, and request timeout is: WordPress option, then environment variable / constant, then default.

= Image description generation =

Passing an image alongside a text prompt (e.g. `AiClient::prompt()->withText(...)->withFile($image)->generateTextResult()`) works for any model this plugin has marked as vision-capable — WordPress's model resolver will automatically route such requests to one of them, whether or not a specific model is requested by ID.

A model is treated as vision-capable if either is true:

* It's listed in the **Vision-Capable Models** setting, or
* LiteLLM's own `GET /model/info` endpoint reports `supports_vision: true` for it.

In practice, LiteLLM rarely knows a self-hosted/proxied model's capabilities on its own (its cost/capability database only recognizes well-known hosted model IDs) — so for most Ollama-backed deployments, the **Vision-Capable Models** setting is the only reliable way to enable this. There is no name-based guessing: an unlisted model is always treated as text-only, to avoid silently sending image data to a model that can't use it. If `/model/info` isn't available on your LiteLLM deployment (older versions, or an API key without access to it), automatic detection is simply skipped — the manual list still works.

Whether the underlying model actually produces a useful description is up to that model/backend, not this plugin — Ollama vision models (e.g. `llava`, or vision-capable Gemma/Llama variants) are required for a meaningful result; text-only models will be rejected by LiteLLM with a "does not support multimodal requests" error if selected.

= Structured JSON output =

Several built-in WordPress AI features ask for a response matching a specific JSON schema (e.g. Content Classification's tag/category suggestions). Only models you mark as structured-output-capable are considered for these -- an unlisted model isn't offered at all, so those features fail with a clear "no connected provider that supports text generation" error rather than a model silently ignoring the schema.

Mark a model as structured-output-capable if either is true:

* It's listed in the **Structured Output-Capable Models** setting, or
* LiteLLM's own `GET /model/info` endpoint reports `supports_response_schema: true` for it.

As with vision, LiteLLM's own metadata is unreliable for self-hosted models (it's `null` for arbitrary Ollama models on every deployment tested so far), so the manual list is what actually matters in practice. Unlike vision, there's no clear error if a model that *shouldn't* be on the list gets added anyway -- verify a candidate model first, since not every model reliably honors `response_format`, even behind the exact same LiteLLM/Ollama backend as a model that does. A model that ignores it entirely returns free-form prose instead of JSON, which then fails to parse on the WordPress side with an error like "Could not parse AI response as valid suggestions" -- if you see that, the model you're using (or the one WordPress auto-selected, if none was requested by name) most likely doesn't actually support structured output and shouldn't be on this list.

= Known limitations =

* **No streaming.** The SDK's OpenAI-compatible text generation base class sends and parses one full response per request; there is no chunked/SSE code path to hook into.
* **No text-to-image generation, embeddings-as-a-first-class-feature, or other modalities yet.** Chat-style text generation (including image-input description, see above) is what's implemented so far.
* **No custom retry/backoff.** Requests use the configured timeout (see above) but a failed or timed-out request is not automatically retried.
* **PHP's `max_execution_time` is only raised on a best-effort basis.** The plugin calls `set_time_limit()` before sending a text generation request, matching it to the configured Request Timeout. This does nothing if your host has disabled `set_time_limit` (some do, via `disable_functions`), and it cannot raise separate hard limits some environments impose outside PHP itself -- e.g. PHP-FPM's `request_terminate_timeout`, or a web server/reverse-proxy read timeout. If generation still fails around a suspiciously round number of seconds (30s and 60s are common defaults) after increasing the Request Timeout setting, check those first.
* **Raising WordPress core's default AI request timeout is site-wide, not LiteLLM-specific.** WP core's built-in AI features (Content Resizing, Alt Text Generation, etc.) apply their own 30-second default via the `wp_ai_client_default_request_timeout` filter, which overrides any provider's own configured timeout and isn't told which provider will end up handling the request. This plugin raises that filter's value to match the Request Timeout setting -- but only ever raises it, never lowers it -- so if you also have a fast cloud provider (OpenAI, Anthropic, etc.) configured, its requests through core Abilities will wait just as long as LiteLLM's before core considers them timed out.
* **Model discovery requests (`GET /v1/models`, `GET /model/info`) have no explicit timeout**, unlike text generation — they use WordPress core's own default HTTP timeout (5 seconds, filterable via the `http_request_timeout` core filter), which can be tight for a slow or cold-starting LiteLLM instance.
* Because LiteLLM can proxy arbitrary, differently-named underlying models, this plugin cannot infer each model's true capabilities from its ID the way a single-vendor provider can. Every model returned by `GET /v1/models` is assumed to support text generation, unless its ID contains "embed", in which case it's assumed to be an embedding model instead. Vision-capability detection is documented separately above.

== Installation ==

1. Install and activate this plugin alongside the official [AI](https://github.com/WordPress/ai) plugin (or on WordPress 7.0+, where the AI Client SDK ships in core).
2. Go to **Settings → Connectors** and enter your LiteLLM API key for "LiteLLM (Ollama)".
3. Go to **Settings → LiteLLM Provider** and set your LiteLLM gateway's base URL and a default model.

== Updates ==

This plugin isn't listed on the WordPress.org plugin directory, so wp-admin's update mechanism doesn't know about it out of the box. It bundles [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) to fill that gap, pointed at a small `update.json` metadata file (currently served from this repository's `main` branch) rather than at any particular Git host's release API — deliberately, so the distribution host can move (e.g. from GitHub to a self-hosted Gitea/Forgejo instance) without touching the plugin's code, only where `update.json` points its `download_url`.

To install: download the ZIP linked from [`update.json`](https://raw.githubusercontent.com/maxheadroom/ai-provider-for-litellm/main/update.json) and upload it via **Plugins → Add New → Upload Plugin**. From then on, updates appear in wp-admin like any other plugin. Publishing a new version means bumping `version` and `download_url` in `update.json` alongside the release — this is a manually maintained file, not auto-generated from tags.

== Changelog ==

= 0.4.0 =
* Add self-hosted update checks via a JSON metadata file (bundled Plugin Update Checker library), so sites installing this plugin outside the WordPress.org directory still get update notices in wp-admin. Deliberately forge-agnostic (a plain metadata URL, not a GitHub/GitLab/BitBucket-specific integration) since none of Plugin Update Checker's built-in VCS integrations support self-hosted Gitea/Forgejo. See "Updates" above.

= 0.3.1 =
* Make structured JSON output opt-in per model (new Structured Output-Capable Models setting), instead of declaring it for every discovered model. Confirmed live: not every self-hosted model reliably honors `response_format` even behind the exact same LiteLLM/Ollama backend as one that does -- an incapable model auto-selected by WordPress's resolver returned free-form prose instead of JSON, which then failed downstream parsing with "Could not parse AI response as valid suggestions." This mirrors the existing Vision-Capable Models pattern (manual list, unioned with LiteLLM's `/model/info` `supports_response_schema` flag when available).

= 0.3.0 =
* Add structured JSON output support (declare `outputMimeType`/`outputSchema` capability), required by several built-in WP AI features -- e.g. Content Classification/tag suggestions previously failed outright with "Term generation failed. Please ensure you have a connected provider that supports text generation." because this provider never declared the capability at all.
* Fix a request-format bug in the SDK's own JSON-schema handling: it places the raw schema directly under `response_format.json_schema`, but OpenAI's actual API (and OpenAI-compatible backends like LiteLLM) expect it wrapped in a `{name, schema}` object. Confirmed live against a real LiteLLM/Ollama backend: the unwrapped form was silently ignored (producing invalid, markdown-fenced non-JSON output); the corrected form produces proper schema-conformant JSON.

= 0.2.2 =
* Raise WordPress core's `wp_ai_client_default_request_timeout` filter to match the configured Request Timeout. Previously, any generation triggered through a built-in WP AI feature (Content Resizing, Alt Text Generation, etc. -- including image description generation below) silently ignored this plugin's Request Timeout setting entirely, because WP core's own prompt-builder wrapper applies a hardcoded 30-second default that takes precedence over a provider's own configured timeout.

= 0.2.1 =
* Merge image description generation with the configurable request timeout and execution-time-limit fix below.

= 0.2.0 =
* Add image description generation: models marked vision-capable (manually, or via LiteLLM's `/model/info`) can now accept image input for text generation.

= 0.1.2 =
* Raise PHP's `max_execution_time` (via `set_time_limit()`) to match the configured Request Timeout before sending a text generation request. Previously, a slow model response could be killed by PHP's own default execution time limit (commonly 30 seconds) well before a longer configured Request Timeout was ever reached.

= 0.1.1 =
* Make the text generation request timeout configurable (Settings → LiteLLM Provider, or `LITELLM_REQUEST_TIMEOUT`), default unchanged at 30 seconds.
* Remove the connect-timeout setting: confirmed it had no effect under WordPress's own HTTP client, which only supports a single overall request timeout.

= 0.1.0 =
* Initial release: text generation, model discovery/availability, and base-URL/default-model settings.
