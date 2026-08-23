# AI Provider for LiteLLM

Contributors:
Tags: ai, litellm, ollama, ai-client
Requires at least: WordPress 7.0
Tested up to: WordPress 7.1
Requires PHP: 7.4
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This plugin adds a `litellm` provider to the WordPress AI Client SDK. The provider connects to a self-hosted [LiteLLM](https://www.litellm.ai/) gateway. The gateway can proxy other backends, for example Ollama.

## Description

This plugin registers a `litellm` provider with the WordPress PHP AI Client SDK. The official AI plugin, and any other AI-Client-based code, can then generate text through your LiteLLM gateway.

Activate the plugin. WordPress core's Connectors API (WP 7.0+) then detects the provider automatically. The provider appears on **Settings → Connectors** as "LiteLLM (Ollama)". Enter your LiteLLM API key there.

Set the gateway's base URL, a default model, and vision-capable models on **Settings → LiteLLM Provider**.

Text Generation supports image input for any model you mark as vision-capable. Use this to generate a text description of an image, for example alt text. See "Image description generation" below.

Text Generation also supports structured JSON output for any model you mark as structured-output-capable. Several built-in WordPress AI features require this, including Content Classification tag suggestions and Type Ahead. See "Structured JSON output" below.

### Configuration

* **API Key** — Set this on **Settings → Connectors**. Or, set the `LITELLM_API_KEY` environment variable or PHP constant.
* **API Base URL** — Set this on **Settings → LiteLLM Provider**. Or, set the `LITELLM_API_BASE` environment variable. The default value is `http://localhost:4000/v1`.
* **Vision-Capable Models** — Set this on **Settings → LiteLLM Provider**. Enter a list of model IDs that accept image input. Put one model ID per line, or separate them with commas.
* **Structured Output-Capable Models** — Set this on **Settings → LiteLLM Provider**. Enter a list of model IDs that reliably return valid JSON that matches the requested schema.
* **Request Timeout** — Set this on **Settings → LiteLLM Provider**. Or, set the `LITELLM_REQUEST_TIMEOUT` environment variable, in seconds. The default value is 30. This setting applies to text generation requests only. Increase it if your local models take longer than 30 seconds to respond.

  The plugin also raises PHP's own `max_execution_time` to match this value before it sends a request. Without this, PHP could stop the request early, regardless of this setting. The plugin also raises WordPress core's site-wide default AI request timeout to at least this value. Without this, core's built-in AI features (Content Resizing, Alt Text Generation, and others) would ignore this setting entirely. See "Known limitations" below for details.

The plugin resolves the API key, base URL, and request timeout in this order:

1. The WordPress option.
2. The environment variable or constant.
3. The default value.

### Image description generation

You can send an image with a text prompt, for example with `AiClient::prompt()->withText(...)->withFile($image)->generateTextResult()`. This works for any model you mark as vision-capable. WordPress's model resolver routes these requests to a vision-capable model automatically. This applies whether or not you request a specific model by ID.

A model is vision-capable when one of these is true:

* The **Vision-Capable Models** setting lists the model.
* LiteLLM's `GET /model/info` endpoint reports `supports_vision: true` for the model.

In most cases, LiteLLM does not know a self-hosted model's capabilities. Its cost/capability database only recognizes well-known hosted model IDs. For most Ollama-backed setups, use the **Vision-Capable Models** setting to enable this feature reliably.

The plugin does not guess capabilities from a model's name. An unlisted model is always text-only. This prevents the plugin from sending image data to a model that cannot use it.

Your LiteLLM deployment may not support the `/model/info` endpoint. Older versions lack it, and some API keys cannot access it. In these cases, the plugin skips automatic detection. The manual list still works.

The result quality depends on the underlying model, not on this plugin. Use a vision-capable Ollama model, for example `llava`, or a vision-capable Gemma or Llama variant. If you select a text-only model, LiteLLM rejects the request with a "does not support multimodal requests" error.

### Structured JSON output

Several built-in WordPress AI features request a response that matches a specific JSON schema. One example is Content Classification's tag and category suggestions.

The plugin only offers models you mark as structured-output-capable for these requests. If no marked model is available, the feature fails with a clear error: "no connected provider that supports text generation." This is intentional. It prevents a model from silently ignoring the requested schema.

A model is structured-output-capable when one of these is true:

* The **Structured Output-Capable Models** setting lists the model.
* LiteLLM's `GET /model/info` endpoint reports `supports_response_schema: true` for the model.

As with vision support, LiteLLM's own metadata is unreliable for self-hosted models. In testing, this value was `null` for every Ollama model on every deployment. In practice, the manual list is what matters.

Verify a candidate model before you add it to the list. Unlike vision support, an incorrect entry produces no clear error. Not every model honors `response_format` reliably, even behind the same LiteLLM/Ollama backend as a model that does.

A model that ignores `response_format` returns free-form text instead of JSON. WordPress then fails to parse the response, with an error like "Could not parse AI response as valid suggestions." If you see this error, the model in use most likely does not support structured output. Remove it from the list. This also applies to a model WordPress auto-selected, if you did not request one by name.

## Known limitations

* **No streaming.** The SDK's OpenAI-compatible text generation base class sends and parses one complete response per request. It has no code path for chunked or SSE responses.
* **No text-to-image generation and no embeddings support yet.** The plugin implements chat-style text generation only, including image-input description (see above).
* **No automatic retry.** A request uses the configured timeout (see above). The plugin does not retry a failed or timed-out request.
* **The `max_execution_time` increase works on a best-effort basis only.** The plugin calls `set_time_limit()` before it sends a text generation request, and sets it to match the configured Request Timeout. Some hosts disable `set_time_limit` through `disable_functions`; on these hosts, this call has no effect. Some environments also enforce separate hard limits outside PHP, for example PHP-FPM's `request_terminate_timeout`, or a web server or reverse-proxy read timeout. The plugin cannot raise these. If generation still fails near a round number of seconds (30 or 60 are common defaults) after you increase the Request Timeout setting, check these other limits first.
* **The WordPress core timeout increase applies site-wide, not only to LiteLLM.** WP core's built-in AI features (Content Resizing, Alt Text Generation, and others) apply their own 30-second default through the `wp_ai_client_default_request_timeout` filter. This filter overrides each provider's own configured timeout, and it does not know which provider will handle a given request. This plugin raises the filter's value to match the Request Timeout setting, but never lowers it. If you also configure a fast cloud provider, for example OpenAI or Anthropic, its requests through core Abilities wait just as long before core treats them as timed out.
* **Model discovery requests have no explicit timeout.** This applies to `GET /v1/models` and `GET /model/info`. Unlike text generation, these requests use WordPress core's default HTTP timeout, 5 seconds by default. You can change this with the core `http_request_timeout` filter. This default can be too short for a slow or cold-starting LiteLLM instance.
* **The plugin cannot infer a model's true capabilities from its ID.** LiteLLM can proxy many different underlying models under arbitrary names, so this plugin cannot use ID conventions the way a single-vendor provider can. The plugin assumes every model returned by `GET /v1/models` supports text generation. The exception: if a model's ID contains "embed", the plugin assumes it is an embedding model instead. See "Image description generation" above for vision-capability detection.

## Installation

1. Install and activate this plugin alongside the official [AI](https://github.com/WordPress/ai) plugin. On WordPress 7.0 and later, the AI Client SDK ships in core, so you do not need the AI plugin.
2. Go to **Settings → LiteLLM Provider**. Set your LiteLLM gateway's base URL and a default model.
3. Go to **Settings → Connectors**. Enter your LiteLLM API key for "LiteLLM (Ollama)".


## Updates

This plugin is not listed on the WordPress.org plugin directory. Because of this, wp-admin's update mechanism does not detect it automatically. The plugin bundles [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) to add update notices.

Plugin Update Checker reads a small `update.json` metadata file. This file is currently served from this repository's `main` branch. The plugin does not use any Git host's release API directly. This design lets the distribution host change, for example from GitHub to a self-hosted Gitea or Forgejo instance, without any code change to the plugin. Only the `download_url` value in `update.json` needs to change.

To install the plugin:

1. Download the ZIP file linked from [Releases](https://repos.mxhdr.net/maxheadroom/ai-provider-for-litellm/releases).
2. Go to **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP file.

After installation, updates appear in wp-admin like any other plugin's updates.

To publish a new version, bump the `version` and `download_url` values in `update.json` when you release. This file is maintained by hand. It is not generated automatically from Git tags.

## Changelog

### 0.4.1
* Added README.md for the git repo
* updated README.md to reflect the correct order of settings
* updated README.md with the Forgejo URL of the plugin
* updated readme.txt accordingly

### 0.4.0
* Add self-hosted update checks. The plugin uses a JSON metadata file and the bundled Plugin Update Checker library. Sites that install this plugin outside the WordPress.org directory now get update notices in wp-admin. This approach is forge-agnostic: it uses a plain metadata URL, not a GitHub-, GitLab-, or BitBucket-specific integration. None of Plugin Update Checker's built-in VCS integrations support self-hosted Gitea or Forgejo. See "Updates" above.

### 0.3.1
* Make structured JSON output opt-in per model. Add the Structured Output-Capable Models setting for this. Previously, the plugin declared this capability for every discovered model. Testing showed that not every self-hosted model honors `response_format` reliably, even behind the same LiteLLM/Ollama backend as a model that does. An incapable model, auto-selected by WordPress's resolver, returned free-form text instead of JSON. This caused a downstream parsing failure: "Could not parse AI response as valid suggestions." This change follows the existing Vision-Capable Models pattern: a manual list, combined with LiteLLM's `/model/info` `supports_response_schema` flag when available.

### 0.3.0
* Add structured JSON output support. The plugin now declares the `outputMimeType` and `outputSchema` capabilities. Several built-in WordPress AI features require this. For example, Content Classification tag suggestions previously failed with "Term generation failed. Please ensure you have a connected provider that supports text generation," because this provider never declared the capability.
* Fix a request-format bug in the SDK's JSON-schema handling. The SDK places the raw schema directly under `response_format.json_schema`. OpenAI's actual API, and OpenAI-compatible backends like LiteLLM, expect the schema wrapped in a `{name, schema}` object instead. Testing against a real LiteLLM/Ollama backend confirmed the bug: the unwrapped form was silently ignored, and produced invalid, markdown-fenced output instead of JSON. The corrected form produces valid JSON that matches the schema.

### 0.2.2
* Raise WordPress core's `wp_ai_client_default_request_timeout` filter to match the configured Request Timeout. Previously, any generation triggered through a built-in WP AI feature, for example Content Resizing or Alt Text Generation, ignored this plugin's Request Timeout setting. This happened because WP core's own prompt-builder wrapper applies a hardcoded 30-second default. This default took priority over a provider's own configured timeout.

### 0.2.1
* Merge image description generation with the configurable request timeout and execution-time-limit fix below.

### 0.2.0
* Add image description generation. A model marked vision-capable, either manually or through LiteLLM's `/model/info` endpoint, can now accept image input for text generation.

### 0.1.2
* Raise PHP's `max_execution_time`, using `set_time_limit()`, to match the configured Request Timeout. The plugin does this before it sends a text generation request. Previously, a slow model response could exceed PHP's own default execution time limit, commonly 30 seconds, well before a longer configured Request Timeout was reached.

### 0.1.1
* Make the text generation request timeout configurable. Set it on **Settings → LiteLLM Provider**, or with the `LITELLM_REQUEST_TIMEOUT` environment variable. The default value stays at 30 seconds.
* Remove the connect-timeout setting. Testing confirmed it had no effect under WordPress's own HTTP client. This client only supports one overall request timeout.

### 0.1.0
* Initial release. This version adds text generation, model discovery and availability checks, and base-URL and default-model settings.
