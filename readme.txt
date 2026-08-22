=== AI Provider for LiteLLM ===
Contributors:
Tags: ai, litellm, ollama, ai-client
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Registers an AI Client provider that talks to a LiteLLM gateway (e.g. backed by Ollama) using the OpenAI-compatible wire protocol.

== Description ==

This plugin registers a `litellm` provider with the WordPress PHP AI Client SDK, so that the official AI plugin (and any other AI-Client-based code) can generate text through a self-hosted [LiteLLM](https://www.litellm.ai/) gateway — for example one proxying local Ollama models.

Once activated, the provider is automatically picked up by WordPress core's Connectors API (WP 7.0+): it appears on **Settings → Connectors** as "LiteLLM (Ollama)", where you can enter your LiteLLM API key. The gateway's base URL, a default model, and vision-capable models are configured separately on **Settings → LiteLLM Provider**.

Text generation supports image input (i.e. describing an image in text, such as generating alt text) for any model you mark as vision-capable — see "Image description generation" below.

= Configuration =

* **API Key** — set on **Settings → Connectors**, or via the `LITELLM_API_KEY` environment variable / PHP constant.
* **API Base URL** — set on **Settings → LiteLLM Provider**, or via the `LITELLM_API_BASE` environment variable. Defaults to `http://localhost:4000/v1`.
* **Vision-Capable Models** — set on **Settings → LiteLLM Provider**: a list of model IDs (one per line, or comma-separated) that accept image input.

Precedence for the API key and base URL is: WordPress option, then environment variable / constant, then default.

= Image description generation =

Passing an image alongside a text prompt (e.g. `AiClient::prompt()->withText(...)->withFile($image)->generateTextResult()`) works for any model this plugin has marked as vision-capable — WordPress's model resolver will automatically route such requests to one of them, whether or not a specific model is requested by ID.

A model is treated as vision-capable if either is true:

* It's listed in the **Vision-Capable Models** setting, or
* LiteLLM's own `GET /model/info` endpoint reports `supports_vision: true` for it.

In practice, LiteLLM rarely knows a self-hosted/proxied model's capabilities on its own (its cost/capability database only recognizes well-known hosted model IDs) — so for most Ollama-backed deployments, the **Vision-Capable Models** setting is the only reliable way to enable this. There is no name-based guessing: an unlisted model is always treated as text-only, to avoid silently sending image data to a model that can't use it. If `/model/info` isn't available on your LiteLLM deployment (older versions, or an API key without access to it), automatic detection is simply skipped — the manual list still works.

Whether the underlying model actually produces a useful description is up to that model/backend, not this plugin — Ollama vision models (e.g. `llava`, or vision-capable Gemma/Llama variants) are required for a meaningful result; text-only models will be rejected by LiteLLM with a "does not support multimodal requests" error if selected.

= Known limitations =

* **No streaming.** The SDK's OpenAI-compatible text generation base class sends and parses one full response per request; there is no chunked/SSE code path to hook into.
* **No text-to-image generation, embeddings-as-a-first-class-feature, or other modalities yet.** Chat-style text generation (including image-input description, see above) is what's implemented so far.
* **No custom retry/backoff.** Requests use explicit timeouts (30s request / 10s connect) but a failed request is not automatically retried.
* Because LiteLLM can proxy arbitrary, differently-named underlying models, this plugin cannot infer each model's true capabilities from its ID the way a single-vendor provider can. Every model returned by `GET /v1/models` is assumed to support text generation, unless its ID contains "embed", in which case it's assumed to be an embedding model instead. Vision-capability detection is documented separately above.

== Installation ==

1. Install and activate this plugin alongside the official [AI](https://github.com/WordPress/ai) plugin (or on WordPress 7.0+, where the AI Client SDK ships in core).
2. Go to **Settings → Connectors** and enter your LiteLLM API key for "LiteLLM (Ollama)".
3. Go to **Settings → LiteLLM Provider** and set your LiteLLM gateway's base URL and a default model.

== Changelog ==

= 0.2.0 =
* Add image description generation: models marked vision-capable (manually, or via LiteLLM's `/model/info`) can now accept image input for text generation.

= 0.1.0 =
* Initial release: text generation, model discovery/availability, and base-URL/default-model settings.
