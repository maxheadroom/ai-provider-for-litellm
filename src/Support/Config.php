<?php
/**
 * Configuration resolution for the LiteLLM provider.
 *
 * @package WordPress\LiteLLMAiProvider
 */

declare(strict_types=1);

namespace WordPress\LiteLLMAiProvider\Support;

/**
 * Resolves plugin configuration with the precedence:
 * WordPress option > environment variable > default.
 */
final class Config {

	public const OPTION_BASE_URL = 'litellm_api_base';

	public const OPTION_DEFAULT_MODEL = 'litellm_default_model';

	public const OPTION_VISION_MODELS = 'litellm_vision_models';

	public const ENV_BASE_URL = 'LITELLM_API_BASE';

	public const DEFAULT_BASE_URL = 'http://localhost:4000/v1';

	/**
	 * Resolves the LiteLLM API base URL.
	 */
	public static function baseUrl(): string {
		$option = get_option( self::OPTION_BASE_URL, '' );

		if ( is_string( $option ) && '' !== $option ) {
			return untrailingslashit( $option );
		}

		$env = getenv( self::ENV_BASE_URL );

		if ( is_string( $env ) && '' !== $env ) {
			return untrailingslashit( $env );
		}

		return untrailingslashit( self::DEFAULT_BASE_URL );
	}

	/**
	 * Resolves the configured default model, if any.
	 */
	public static function defaultModel(): string {
		$option = get_option( self::OPTION_DEFAULT_MODEL, '' );

		return is_string( $option ) ? $option : '';
	}

	/**
	 * Resolves the admin-configured list of vision-capable model IDs.
	 *
	 * @return array<string, true> Model IDs as keys, for O(1) lookup.
	 */
	public static function visionModelIds(): array {
		$option = get_option( self::OPTION_VISION_MODELS, '' );

		if ( ! is_string( $option ) || '' === $option ) {
			return [];
		}

		$ids = preg_split( '/[,\n\r]+/', $option );
		$ids = false === $ids ? [] : $ids;
		$ids = array_map( 'trim', $ids );
		$ids = array_filter( $ids, static fn( string $id ): bool => '' !== $id );

		return array_fill_keys( $ids, true );
	}
}
