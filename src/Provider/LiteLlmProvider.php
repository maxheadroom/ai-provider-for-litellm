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
	 * Builds the request options (timeout) used for outgoing HTTP requests.
	 *
	 * Only a request timeout is set. A connect timeout was previously set here too, but
	 * WordPress's own PSR-18 client adapter (WP_AI_Client_HTTP_Client, which wraps
	 * wp_safe_remote_request()) only maps RequestOptions::getTimeout() and
	 * getMaxRedirects() to WP HTTP API args -- there's no separate connect-timeout
	 * concept in wp_remote_request(), so a configured connect timeout was silently
	 * having no effect in a real WordPress environment.
	 */
	private static function createRequestOptions(): RequestOptions {
		$options = new RequestOptions();
		$options->setTimeout( Config::requestTimeout() );
		return $options;
	}
}
