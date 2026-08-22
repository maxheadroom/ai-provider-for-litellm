<?php
/**
 * LiteLLM AI Client provider.
 *
 * @package WordPress\LiteLLMAiProvider
 */

declare(strict_types=1);

namespace WordPress\LiteLLMAiProvider\Provider;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\LiteLLMAiProvider\Metadata\LiteLlmModelMetadataDirectory;
use WordPress\LiteLLMAiProvider\Models\LiteLlmTextGenerationModel;
use WordPress\LiteLLMAiProvider\Support\Config;

/**
 * AI Client provider for a LiteLLM gateway (OpenAI-compatible, e.g. backed by Ollama).
 */
final class LiteLlmProvider extends AbstractApiProvider {

	public const ID = 'litellm';

	/**
	 * Request timeout in seconds. See SPEC §4.2.
	 */
	private const REQUEST_TIMEOUT = 30.0;

	/**
	 * Connection timeout in seconds. See SPEC §4.2.
	 */
	private const CONNECT_TIMEOUT = 10.0;

	/**
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		return Config::baseUrl();
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		return new ProviderMetadata(
			self::ID,
			__( 'LiteLLM (Ollama)', 'ai-provider-for-litellm' ),
			ProviderTypeEnum::server(),
			admin_url( 'options-general.php?page=litellm-provider' ),
			RequestAuthenticationMethod::apiKey(),
			__( 'AI Provider for LiteLLM-backed, OpenAI-compatible endpoints (e.g. Ollama).', 'ai-provider-for-litellm' ),
			null
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new ListModelsApiBasedProviderAvailability( self::modelMetadataDirectory() );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new LiteLlmModelMetadataDirectory();
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModel( ModelMetadata $modelMetadata, ProviderMetadata $providerMetadata ): ModelInterface {
		foreach ( $modelMetadata->getSupportedCapabilities() as $capability ) {
			if ( $capability->isTextGeneration() ) {
				$model = new LiteLlmTextGenerationModel( $modelMetadata, $providerMetadata );
				$model->setRequestOptions( self::createRequestOptions() );
				return $model;
			}
		}

		throw new RuntimeException(
			sprintf(
				'Model "%s" has no capability implemented by the LiteLLM provider.',
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
				$modelMetadata->getId()
			)
		);
	}

	/**
	 * Builds the request options (timeouts) used for outgoing HTTP requests.
	 */
	private static function createRequestOptions(): RequestOptions {
		$options = new RequestOptions();
		$options->setTimeout( self::REQUEST_TIMEOUT );
		$options->setConnectTimeout( self::CONNECT_TIMEOUT );
		return $options;
	}
}
