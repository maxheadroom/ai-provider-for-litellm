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
	 * {@inheritDoc}
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = [], $data = null ): Request {
		return new Request( $method, LiteLlmProvider::url( $path ), $headers, $data, $this->getRequestOptions() );
	}
}
