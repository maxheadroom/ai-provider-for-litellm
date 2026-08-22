<?php
/**
 * LiteLLM model metadata directory.
 *
 * @package WordPress\LiteLLMAiProvider
 */

declare(strict_types=1);

namespace WordPress\LiteLLMAiProvider\Metadata;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;
use WordPress\LiteLLMAiProvider\Provider\LiteLlmProvider;

/**
 * Discovers models exposed by LiteLLM's OpenAI-compatible `GET /v1/models` endpoint.
 *
 * LiteLLM proxies arbitrary, heterogeneous model names (e.g. `ollama/llama3`), so unlike
 * a single fixed-catalogue provider we can't infer capabilities from known model-ID
 * patterns. Every discovered model is assumed to support text generation, unless its ID
 * looks like an embedding model.
 */
final class LiteLlmModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * {@inheritDoc}
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = [], $data = null ): Request {
		return new Request( $method, LiteLlmProvider::url( $path ), $headers, $data );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
		$data    = $response->getData();
		$entries = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : [];

		$models = [];
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['id'] ) || ! is_string( $entry['id'] ) || '' === $entry['id'] ) {
				continue;
			}

			$id       = $entry['id'];
			$models[] = new ModelMetadata( $id, $id, $this->capabilitiesFor( $id ), $this->optionsFor( $id ) );
		}

		return $models;
	}

	/**
	 * Determines the capabilities to assign for a discovered model ID.
	 *
	 * @param string $modelId The model ID.
	 * @return list<CapabilityEnum>
	 */
	private function capabilitiesFor( string $modelId ): array {
		if ( $this->looksLikeEmbeddingModel( $modelId ) ) {
			return [ CapabilityEnum::embeddingGeneration() ];
		}

		return [ CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ];
	}

	/**
	 * Determines the supported options to assign for a discovered model ID.
	 *
	 * @param string $modelId The model ID.
	 * @return list<SupportedOption>
	 */
	private function optionsFor( string $modelId ): array {
		if ( $this->looksLikeEmbeddingModel( $modelId ) ) {
			return [
				new SupportedOption( OptionEnum::inputModalities(), [ [ ModalityEnum::text() ] ] ),
			];
		}

		return [
			new SupportedOption( OptionEnum::inputModalities(), [ [ ModalityEnum::text() ] ] ),
			new SupportedOption( OptionEnum::outputModalities(), [ [ ModalityEnum::text() ] ] ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
		];
	}

	/**
	 * Heuristic for whether a model ID looks like an embedding model.
	 */
	private function looksLikeEmbeddingModel( string $modelId ): bool {
		return false !== strpos( strtolower( $modelId ), 'embed' );
	}
}
