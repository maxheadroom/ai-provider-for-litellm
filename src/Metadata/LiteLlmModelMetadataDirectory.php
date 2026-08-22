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
use WordPress\LiteLLMAiProvider\Support\Config;
use Throwable;

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
	 * Model IDs (as keys) known to accept image input, resolved fresh for each
	 * `sendListModelsRequest()` call. See `resolveVisionCapableModelIds()`.
	 *
	 * @var array<string, true>
	 */
	private array $visionCapableModelIds = [];

	/**
	 * {@inheritDoc}
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = [], $data = null ): Request {
		return new Request( $method, LiteLlmProvider::url( $path ), $headers, $data );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function sendListModelsRequest(): array {
		$this->visionCapableModelIds = $this->resolveVisionCapableModelIds();
		return parent::sendListModelsRequest();
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

		$inputModalitySets = [ [ ModalityEnum::text() ] ];
		if ( isset( $this->visionCapableModelIds[ $modelId ] ) ) {
			$inputModalitySets[] = [ ModalityEnum::text(), ModalityEnum::image() ];
		}

		return [
			new SupportedOption( OptionEnum::inputModalities(), $inputModalitySets ),
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

	/**
	 * Resolves the set of model IDs to treat as vision-capable: the admin-configured
	 * override list, unioned with any model LiteLLM's own metadata reports as such.
	 *
	 * @return array<string, true>
	 */
	private function resolveVisionCapableModelIds(): array {
		$ids = Config::visionModelIds();

		foreach ( $this->fetchModelInfoVisionFlags() as $id => $supportsVision ) {
			if ( $supportsVision ) {
				$ids[ $id ] = true;
			}
		}

		return $ids;
	}

	/**
	 * Fetches LiteLLM's proxy-specific `GET /model/info` endpoint and extracts each
	 * model's `supports_vision` flag, where reported.
	 *
	 * This endpoint is a LiteLLM extension, not part of the OpenAI API spec — some
	 * deployments or API keys may not have access to it. Any failure here (missing
	 * route, insufficient permissions, malformed response) must not break ordinary
	 * model discovery, so it degrades to "no automatic signal" rather than throwing.
	 *
	 * @return array<string, bool> Model ID => supports_vision.
	 */
	private function fetchModelInfoVisionFlags(): array {
		try {
			$request  = $this->createRequest( HttpMethodEnum::GET(), 'model/info' );
			$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
			$response = $this->getHttpTransporter()->send( $request );

			if ( ! $response->isSuccessful() ) {
				return [];
			}

			$data    = $response->getData();
			$entries = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : [];

			$flags = [];
			foreach ( $entries as $entry ) {
				if ( ! is_array( $entry ) || ! isset( $entry['model_name'] ) || ! is_string( $entry['model_name'] ) ) {
					continue;
				}

				$modelInfo      = $entry['model_info'] ?? null;
				$supportsVision = is_array( $modelInfo ) ? ( $modelInfo['supports_vision'] ?? null ) : null;

				$flags[ $entry['model_name'] ] = true === $supportsVision;
			}

			return $flags;
		} catch ( Throwable $e ) {
			return [];
		}
	}
}
