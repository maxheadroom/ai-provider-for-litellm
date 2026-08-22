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
	 * `sendListModelsRequest()` call. See `resolveCapableModelIds()`.
	 *
	 * @var array<string, true>
	 */
	private array $visionCapableModelIds = [];

	/**
	 * Model IDs (as keys) known to reliably honor structured JSON output, resolved
	 * fresh for each `sendListModelsRequest()` call. See `resolveCapableModelIds()`.
	 *
	 * @var array<string, true>
	 */
	private array $jsonCapableModelIds = [];

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
		$modelInfo = $this->fetchModelInfo();

		$this->visionCapableModelIds = $this->resolveCapableModelIds( Config::visionModelIds(), $modelInfo, 'supports_vision' );
		$this->jsonCapableModelIds   = $this->resolveCapableModelIds( Config::jsonModelIds(), $modelInfo, 'supports_response_schema' );

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

		$options = [
			new SupportedOption( OptionEnum::inputModalities(), $inputModalitySets ),
			new SupportedOption( OptionEnum::outputModalities(), [ [ ModalityEnum::text() ] ] ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
		];

		// JSON-mode/structured output (WordPress core's own Content Classification,
		// Type Ahead, etc. abilities require this to consider a provider usable at all).
		// Not every self-hosted model reliably honors `response_format` even via the same
		// LiteLLM/Ollama backend -- confirmed live: some silently ignore it and return
		// free-form prose, which then fails downstream JSON parsing -- so, like vision,
		// this is opt-in per model rather than assumed for every discovered model.
		if ( isset( $this->jsonCapableModelIds[ $modelId ] ) ) {
			$options[] = new SupportedOption( OptionEnum::outputMimeType() );
			$options[] = new SupportedOption( OptionEnum::outputSchema() );
		}

		return $options;
	}

	/**
	 * Heuristic for whether a model ID looks like an embedding model.
	 */
	private function looksLikeEmbeddingModel( string $modelId ): bool {
		return false !== strpos( strtolower( $modelId ), 'embed' );
	}

	/**
	 * Resolves a set of model IDs known to support a given capability: the
	 * admin-configured override list, unioned with any model LiteLLM's own
	 * `/model/info` metadata reports as such via the given field.
	 *
	 * @param array<string, true>                     $override  Admin-configured model IDs.
	 * @param array<string, array<string, bool|null>> $modelInfo Per-model `/model/info` fields, see `fetchModelInfo()`.
	 * @param string                                  $field     The `model_info` field to check (e.g. `supports_vision`).
	 * @return array<string, true>
	 */
	private function resolveCapableModelIds( array $override, array $modelInfo, string $field ): array {
		foreach ( $modelInfo as $id => $fields ) {
			if ( true === ( $fields[ $field ] ?? null ) ) {
				$override[ $id ] = true;
			}
		}

		return $override;
	}

	/**
	 * Fetches LiteLLM's proxy-specific `GET /model/info` endpoint and extracts each
	 * model's relevant capability flags, where reported.
	 *
	 * This endpoint is a LiteLLM extension, not part of the OpenAI API spec — some
	 * deployments or API keys may not have access to it. Any failure here (missing
	 * route, insufficient permissions, malformed response) must not break ordinary
	 * model discovery, so it degrades to "no automatic signal" rather than throwing.
	 *
	 * @return array<string, array{supports_vision: bool, supports_response_schema: bool}> Model ID => capability flags.
	 */
	private function fetchModelInfo(): array {
		try {
			$request  = $this->createRequest( HttpMethodEnum::GET(), 'model/info' );
			$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
			$response = $this->getHttpTransporter()->send( $request );

			if ( ! $response->isSuccessful() ) {
				return [];
			}

			$data    = $response->getData();
			$entries = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : [];

			$info = [];
			foreach ( $entries as $entry ) {
				if ( ! is_array( $entry ) || ! isset( $entry['model_name'] ) || ! is_string( $entry['model_name'] ) ) {
					continue;
				}

				$modelInfo = $entry['model_info'] ?? null;
				$modelInfo = is_array( $modelInfo ) ? $modelInfo : [];

				$info[ $entry['model_name'] ] = [
					'supports_vision'          => true === ( $modelInfo['supports_vision'] ?? null ),
					'supports_response_schema' => true === ( $modelInfo['supports_response_schema'] ?? null ),
				];
			}

			return $info;
		} catch ( Throwable $e ) {
			return [];
		}
	}
}
