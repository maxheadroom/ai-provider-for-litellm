=== AI Provider for LiteLLM ===
Contributors:
Tags: ai, litellm, ollama, ai-client
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Registers an AI Client provider that talks to a LiteLLM gateway (e.g. backed by Ollama) using the OpenAI-compatible wire protocol.

== Description ==

This plugin registers a `litellm` provider with the WordPress PHP AI Client SDK, so that the official AI plugin (and any other AI-Client-based code) can generate text through a self-hosted [LiteLLM](https://www.litellm.ai/) gateway — for example one proxying local Ollama models.

Once activated, the provider is automatically picked up by WordPress core's Connectors API (WP 7.0+): it appears on **Settings → Connectors** as "LiteLLM (Ollama)", where you can enter your LiteLLM API key. The gateway's base URL and a default model are configured separately on **Settings → LiteLLM Provider**.

= Configuration =

* **API Key** — set on **Settings → Connectors**, or via the `LITELLM_API_KEY` environment variable / PHP constant.
* **API Base URL** — set on **Settings → LiteLLM Provider**, or via the `LITELLM_API_BASE` environment variable. Defaults to `http://localhost:4000/v1`.

Precedence for both is: WordPress option, then environment variable / constant, then default.

= Known limitations (v1) =

* **No streaming.** The SDK's OpenAI-compatible text generation base class sends and parses one full response per request; there is no chunked/SSE code path to hook into.
* **No image generation/description, embeddings, or other modalities yet.** Only chat-style text generation is implemented in this version.
* **No custom retry/backoff.** Requests use explicit timeouts (30s request / 10s connect) but a failed request is not automatically retried.
* Because LiteLLM can proxy arbitrary, differently-named underlying models, this plugin cannot infer each model's true capabilities from its ID the way a single-vendor provider can. Every model returned by `GET /v1/models` is assumed to support text generation, unless its ID contains "embed", in which case it's assumed to be an embedding model instead.

== Installation ==

1. Install and activate this plugin alongside the official [AI](https://github.com/WordPress/ai) plugin (or on WordPress 7.0+, where the AI Client SDK ships in core).
2. Go to **Settings → Connectors** and enter your LiteLLM API key for "LiteLLM (Ollama)".
3. Go to **Settings → LiteLLM Provider** and set your LiteLLM gateway's base URL and a default model.

== Changelog ==

= 0.1.0 =
* Initial release: text generation, model discovery/availability, and base-URL/default-model settings.
