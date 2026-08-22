<?php
/**
 * LiteLLM text generation model.
 *
 * @package WordPress\LiteLLMAiProvider
 */

declare(strict_types=1);

namespace WordPress\LiteLLMAiProvider\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\LiteLLMAiProvider\Provider\LiteLlmProvider;

/**
 * Text generation model backed by LiteLLM's OpenAI-compatible `/chat/completions` endpoint.
 *
 * All payload construction and response parsing is inherited from the SDK's
 * OpenAI-compatible base class; this class only needs to build the request.
 */
final class LiteLlmTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * Extra seconds added on top of the configured HTTP timeout when raising PHP's
	 * execution time limit, to leave headroom for response parsing overhead.
	 */
	private const EXECUTION_TIME_BUFFER = 5;

	/**
	 * {@inheritDoc}
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = [], $data = null ): Request {
		$this->extendExecutionTimeLimit();
		return new Request( $method, LiteLlmProvider::url( $path ), $headers, $data, $this->getRequestOptions() );
	}

	/**
	 * Raises PHP's max_execution_time to accommodate the configured HTTP timeout.
	 *
	 * Local/self-hosted models can easily take longer to respond than PHP's default
	 * max_execution_time (commonly 30s), which would otherwise fatally terminate the
	 * whole request before our own, longer-configured HTTP timeout is ever reached.
	 *
	 * This only affects PHP's own execution-time counter; it cannot raise a hard
	 * process-level limit some environments impose separately (e.g. PHP-FPM's
	 * `request_terminate_timeout`, or a web server/reverse-proxy read timeout) --
	 * those must still be adjusted at the hosting level if they're shorter than the
	 * configured request timeout.
	 */
	private function extendExecutionTimeLimit(): void {
		if ( ! function_exists( 'set_time_limit' ) ) {
			return;
		}

		$timeout = null !== $this->getRequestOptions() ? $this->getRequestOptions()->getTimeout() : null;

		if ( null === $timeout ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- set_time_limit() has no error return to check; suppressing avoids a warning when hosts disable it via disable_functions.
		@set_time_limit( (int) ceil( $timeout ) + self::EXECUTION_TIME_BUFFER );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Overridden to fix a request-format bug in the SDK's base implementation: it places
	 * the raw JSON schema directly as the value of `json_schema`, but OpenAI's actual API
	 * (and OpenAI-compatible backends like LiteLLM that follow it) expects it wrapped in
	 * an object with `name`/`schema` keys. Confirmed live against a real LiteLLM/Ollama
	 * backend: the SDK's unwrapped envelope is silently ignored (the model falls back to
	 * free-form, markdown-fenced output that isn't valid JSON), while this wrapped form
	 * is correctly honored and produces schema-conformant JSON.
	 */
	protected function prepareResponseFormatParam( ?array $outputSchema ): array {
		if ( is_array( $outputSchema ) ) {
			return [
				'type'        => 'json_schema',
				'json_schema' => [
					'name'   => 'response',
					'schema' => $outputSchema,
				],
			];
		}

		return [
			'type' => 'json_object',
		];
	}
}
