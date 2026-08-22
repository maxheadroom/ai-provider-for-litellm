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

	public const OPTION_JSON_MODELS = 'litellm_json_models';

	public const OPTION_REQUEST_TIMEOUT = 'litellm_request_timeout';

	public const ENV_BASE_URL = 'LITELLM_API_BASE';

	public const ENV_REQUEST_TIMEOUT = 'LITELLM_REQUEST_TIMEOUT';

	public const DEFAULT_BASE_URL = 'http://localhost:4000/v1';

	public const DEFAULT_REQUEST_TIMEOUT = 30.0;

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
		return self::parseModelIdList( self::OPTION_VISION_MODELS );
	}

	/**
	 * Resolves the admin-configured list of model IDs known to reliably honor
	 * structured JSON output (`response_format`).
	 *
	 * @return array<string, true> Model IDs as keys, for O(1) lookup.
	 */
	public static function jsonModelIds(): array {
		return self::parseModelIdList( self::OPTION_JSON_MODELS );
	}

	/**
	 * Reads a WP option containing a comma/newline-separated list of model IDs.
	 *
	 * @return array<string, true> Model IDs as keys, for O(1) lookup.
	 */
	private static function parseModelIdList( string $optionName ): array {
		$option = get_option( $optionName, '' );

		if ( ! is_string( $option ) || '' === $option ) {
			return [];
		}

		$ids = preg_split( '/[,\n\r]+/', $option );
		$ids = false === $ids ? [] : $ids;
		$ids = array_map( 'trim', $ids );
		$ids = array_filter( $ids, static fn( string $id ): bool => '' !== $id );

		return array_fill_keys( $ids, true );
	}

	/**
	 * Resolves the text generation request timeout, in seconds.
	 */
	public static function requestTimeout(): float {
		$option = get_option( self::OPTION_REQUEST_TIMEOUT, '' );

		if ( is_numeric( $option ) && (float) $option > 0 ) {
			return (float) $option;
		}

		$env = getenv( self::ENV_REQUEST_TIMEOUT );

		if ( is_string( $env ) && is_numeric( $env ) && (float) $env > 0 ) {
			return (float) $env;
		}

		return self::DEFAULT_REQUEST_TIMEOUT;
	}
}
