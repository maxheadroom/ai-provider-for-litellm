# SPEC: WordPress AI Provider Plugin for LiteLLM/Ollama

## 1. Purpose and Scope

This specification describes a WordPress AI Provider plugin that integrates the WordPress PHP AI Client SDK and Connectors API with a LiteLLM gateway exposing an OpenAI‑compatible HTTP API, which itself routes requests to locally or remotely hosted models (e.g. via Ollama).

The plugin must:
- Register a new AI provider usable by the WordPress AI Client and the official AI plugin.
- Talk to a configurable LiteLLM endpoint using the OpenAI wire protocol.
- Discover and expose available models from LiteLLM.
- Support text generation and image description generation as a minimum; other modalities are optional extensions.

The document is intended for implementation by a coding agent or human developer familiar with PHP, WordPress plugin development, and HTTP APIs.

## 2. High‑Level Architecture

### 2.1 Components

- **WordPress plugin**
  - `plugin.php` (entry point and provider registration).
  - `composer.json` (package metadata and autoloading).
  - `src/Provider/LiteLLMProvider.php` (AI Client provider implementation).
  - `src/Availability/LiteLLMProviderAvailability.php` (model discovery / availability).
  - Optional: `src/Admin/SettingsPage.php` (admin UI for configuration).

- **External services**
  - **LiteLLM gateway**: exposes OpenAI‑compatible endpoints (`/v1/chat/completions`, `/v1/completions`, `/v1/models`, etc.).
  - **Model backends**: e.g. Ollama, other OpenAI‑compatible providers configured inside LiteLLM.

### 2.2 Data Flow (Text Generation)

1. A WordPress feature (e.g. AI plugin, custom code, or WP‑CLI) calls the PHP AI Client to generate text, specifying provider ID `litellm` and a model name.
2. The AI Client routes the request to `LiteLLMProvider`, which constructs an HTTP request in the OpenAI format.
3. `LiteLLMProvider` sends the request to the configured LiteLLM endpoint (`LITELLM_API_BASE`) with authentication (`LITELLM_API_KEY` or equivalent).
4. LiteLLM forwards the request to a configured backend (e.g. Ollama) and returns an OpenAI‑style response.
5. `LiteLLMProvider` parses the response and returns a text result object to the AI Client.

### 2.3 Data Flow (Model Discovery)

1. The AI Client calls the provider availability component to list supported models.
2. `LiteLLMProviderAvailability` sends `GET /v1/models` to the LiteLLM endpoint.
3. It maps the returned model list to the internal representation expected by the AI Client (model ID, type, capabilities, status).

## 3. Functional Requirements

### 3.1 Provider Registration

- Register a provider with the AI Client using a short identifier: `litellm`.
- Registration must occur on an early WordPress hook (e.g. `init`) in `plugin.php`.
- The provider must be discoverable through the AI Client registry and usable by the official AI plugin without code changes.

### 3.2 Connectors API Integration

- Register an AI connector using the WordPress Connectors API.
- Connector metadata:
  - Name: `LiteLLM (Ollama)`.
  - Type: `ai_provider`.
  - Description: “AI Provider for LiteLLM‑backed, OpenAI‑compatible endpoints (e.g. Ollama).”
  - Authentication: API key.
  - Base URL: URL of the LiteLLM endpoint
  - Plugin file reference: `plugin.php`.
- Ensure the connector appears in the Connectors screen and can be selected as the site’s active AI provider.

### 3.3 Text Generation

- Support at least one text generation capability (chat or completion).
- Implement a mapping from AI Client prompt objects to OpenAI chat/completion payloads:
  - Messages array (role: `system`/`user`/`assistant`).
  - Model name (as configured via LiteLLM).
  - Sampling parameters: `temperature`, `top_p`, `max_tokens`, etc.
- Support both non‑streaming and streaming responses:
  - Non‑streaming: wait for the full JSON response, extract `choices[0].message.content` or `choices[0].text`.
  - Streaming (optional, but recommended): handle chunked responses in the OpenAI `data:` event format and forward token updates to the AI Client if its API supports streaming.

### 3.4 Model Discovery and Availability

- Implement `LiteLLMProviderAvailability` to:
  - Call `GET {LITELLM_API_BASE}/v1/models`.
  - Parse the JSON response (typically `{ data: [ { id, ... }, ... ] }`).
  - Translate each model into the AI Client’s internal model descriptor.
  - Mark models as available/unavailable based on API response.
- Expose model metadata for use by the AI plugin (e.g. model ID, display name, type: chat, completion, embedding).

### 3.5 Configuration Management

- Primary configuration sources:
  - Environment variables: `LITELLM_API_BASE` and `LITELLM_API_KEY`.
  - WordPress options: allow overriding defaults via admin UI.
- Configuration precedence:
  1. WordPress options (if set).
  2. Environment variables.
  3. Sensible defaults (e.g. `http://localhost:4000/v1` as base, no key if LiteLLM is configured without auth).
- Implement validation:
  - Check that `LITELLM_API_BASE` is a valid URL.
  - Optional: connectivity test (simple `GET /v1/models`).

### 3.6 Admin UI (Optional but Recommended)

- Provide a settings page under `Settings → LiteLLM Provider`.
- Fields:
  - LiteLLM API Base URL.
  - LiteLLM API Key.
  - Default model (e.g. `ollama/llama3`), selected from discovered models.
  - Toggle for enabling/disabling streaming.
- Actions:
  - “Test Connection” button that hits `/v1/models` and shows success/error.
  - “Save Changes” button storing values in WordPress options.

### 3.7 Error Handling

- Handle common HTTP and API errors:
  - Network failures: timeouts, connection refused.
  - HTTP 4xx/5xx responses from LiteLLM.
  - Malformed JSON or unexpected response shape.
- Map errors to AI Client exceptions or error codes.
- Include actionable messages where possible (e.g. “Check LiteLLM API base URL and key”).

### 3.8 Security and Privacy

- Never log full prompts or responses at `INFO` level in production; use debug‑level logging only when explicitly enabled.
- Ensure API keys are stored using WordPress options with `autoload = no` and sanitized input.
- Support transport security:
  - Encourage HTTPS for LiteLLM endpoint.
  - Do not disable SSL verification by default.

### 3.9 Compatibility

- WordPress:
  - Minimum version: 7.0 (for Connectors API and AI integration).
- PHP:
  - Minimum version: 7.4 (matching official AI provider plugins).
- AI Client SDK:
  - Depend on the same major version as the official providers.

## 4. Non‑Functional Requirements

### 4.1 Performance

- Optimize HTTP calls:
  - Use persistent connections where possible.
  - Avoid blocking main thread in admin screens (e.g. use AJAX for connectivity tests).
- Streaming should deliver tokens with minimal buffering when enabled.

### 4.2 Reliability

- Implement reasonable timeouts for HTTP requests (e.g. 30 seconds, configurable).
- Support retries for transient failures (e.g. network hiccups), with a small backoff.

### 4.3 Extensibility

- Code structure should allow:
  - Adding embeddings support via `/v1/embeddings`.
  - Adding image generation via OpenAI‑style image endpoints.
  - Supporting tool‑calling/function‑calling without major refactors (pass tool specs through unchanged to LiteLLM).

## 5. Detailed Design

### 5.1 File and Namespace Layout

- Plugin slug: `ai-provider-for-litellm`.
- Namespace: `WordPress\LiteLLMAiProvider`.
- Files:
  - `plugin.php` (WordPress plugin bootstrap).
  - `composer.json` (package metadata, PSR‑4 autoloading).
  - `src/Provider/LiteLLMProvider.php`.
  - `src/Availability/LiteLLMProviderAvailability.php`.
  - `src/Admin/SettingsPage.php` (optional).

### 5.2 plugin.php Responsibilities

- Define plugin headers (name, description, version, requires PHP, requires WordPress).
- Autoload classes via Composer (`require_once __DIR__ . '/vendor/autoload.php';`).
- Hook into `init` (or appropriate hook) to:
  - Initialize the AI Client registry.
  - Register `LiteLLMProvider` with the registry.
- Register connector with Connectors API during an appropriate hook.

### 5.3 LiteLLMProvider Responsibilities

- Implement required AI Client provider interface/abstract class.
- Provide factory methods for:
  - Creating text generation model objects.
  - Returning provider availability implementation.
- Implement HTTP client logic:
  - Build OpenAI‑format payloads.
  - Send requests to LiteLLM endpoint using WordPress HTTP API (`wp_remote_post`) or Guzzle (depending on AI Client conventions).
  - Parse responses and map to AI Client result objects.

### 5.4 LiteLLMProviderAvailability Responsibilities

- Implement model discovery:
  - Call `/v1/models`.
  - Parse `data[]` array.
  - Map each model to AI Client model descriptor.
- Provide caching layer:
  - Cache model list for a configurable TTL (e.g. 5 minutes) in a transient.

### 5.5 SettingsPage Responsibilities (Optional)

- Render admin settings screen.
- Register settings:
  - `litellm_api_base`.
  - `litellm_api_key`.
  - `litellm_default_model`.
  - `litellm_enable_streaming`.
- Validate and sanitize inputs.
- Provide connectivity test using AJAX.

## 6. Configuration Examples

### 6.1 Environment‑Only Configuration

- `.env` or environment:
  - `LITELLM_API_BASE=https://example.com/v1`
  - `LITELLM_API_KEY=supersecret`
- WordPress options: not set; plugin uses environment values.

### 6.2 WordPress Options Override

- Environment:
  - `LITELLM_API_BASE=http://localhost:4000/v1`
- WordPress options:
  - `litellm_api_base=https://example.com/v1` (takes precedence).

## 7. Testing Strategy

### 7.1 Unit Tests

- Test payload construction: verify mapping from AI Client prompt objects to OpenAI payloads.
- Test response parsing: verify mapping from OpenAI responses to AI Client result objects.

### 7.2 Integration Tests

- With a real LiteLLM endpoint running against Ollama:
  - Test `/v1/models` discovery.
  - Test basic chat completion.
  - Test streaming (if implemented).

### 7.3 WordPress‑Level Tests

- Activate plugin and verify:
  - Provider appears in the AI Client registry.
  - Connector appears in Connectors screen.
  - AI plugin can use the provider to generate text.

## 8. Implementation Notes for Coding Agent

- Use official AI provider plugins (OpenAI, Anthropic, Google, OpenRouter) as structural and API references for how to implement provider classes and connectors.
- Where exact AI Client interfaces or Connectors API functions are unknown, follow the conventions used in those plugins and the official WordPress developer documentation.
- Keep the implementation modular:
  - Separate concerns (provider logic, availability, admin UI).
  - Avoid hard‑coding model IDs; rely on `/v1/models`.
- Ensure all new code passes WordPress coding standards (PHPCS) and basic static analysis (PHPStan) if feasible.


# Reference Material
Existing WordPress AI connectors:
- OpenAI connector https://github.com/WordPress/ai-provider-for-openai 
- Anthropic connector https://github.com/WordPress/ai-provider-for-anthropic
- Google AI connector https://github.com/WordPress/ai-provider-for-google 
- AI plugin for WordPress ( https://github.com/WordPress/ai
