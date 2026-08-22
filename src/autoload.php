<?php
/**
 * Hand-rolled PSR-4 autoloader for this plugin's own classes.
 *
 * The plugin does not ship a vendor/ directory (the AI Client SDK is
 * expected to already be available via WordPress core or the core AI
 * plugin), so we can't rely on vendor/autoload.php here.
 *
 * @package WordPress\LiteLLMAiProvider
 */

declare(strict_types=1);

namespace WordPress\LiteLLMAiProvider;

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';

		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$file           = __DIR__ . '/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);
