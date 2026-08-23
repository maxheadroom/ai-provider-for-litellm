<?php
/**
 * Plugin Name:       AI Provider for LiteLLM
 * Description:       Registers an AI Client provider that talks to a LiteLLM gateway (e.g. backed by Ollama) using the OpenAI-compatible wire protocol.
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Version:           0.4.0
 * Author:            Falko Zurell + Claude Code
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-provider-for-litellm
 *
 * @package WordPress\LiteLLMAiProvider
 */

declare(strict_types=1);

namespace WordPress\LiteLLMAiProvider;

use WordPress\AiClient\AiClient;
use WordPress\LiteLLMAiProvider\Admin\SettingsPage;
use WordPress\LiteLLMAiProvider\Provider\LiteLlmProvider;
use WordPress\LiteLLMAiProvider\Support\Config;
use WordPress\LiteLLMAiProvider\Support\UpdateChecker;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Registers the LiteLLM provider with the AI Client's default registry.
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();

	if ( $registry->hasProvider( LiteLlmProvider::class ) ) {
		return;
	}

	$registry->registerProvider( LiteLlmProvider::class );
}
add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );

/**
 * Raises WordPress core's own default AI request timeout to match this plugin's setting.
 *
 * WP core's `WP_AI_Client_Prompt_Builder` (used by every built-in "Ability" -- Content
 * Resizing, Alt Text Generation, etc. -- via `wp_ai_client_prompt()`) unconditionally
 * applies its own 30-second default RequestOptions in its constructor. Because the SDK's
 * ModelResolver gives resolver-level RequestOptions precedence over whatever a provider's
 * own model sets, that 30s default silently overrides our Request Timeout setting for any
 * generation triggered through a core Ability -- this plugin's own model-level timeout
 * (LiteLlmProvider::createModel()) only ever applies to code that resolves a model without
 * going through that wrapper (e.g. direct AiClient::prompt() usage, WP-CLI).
 *
 * This is WP core's own sanctioned extension point for exactly this ("Filters the default
 * request timeout in seconds for AI Client HTTP requests"). Note this filter is global and
 * provider-agnostic -- it isn't told which provider will end up handling the request -- so
 * raising it here affects every AI provider's Ability-driven requests on the site, not only
 * LiteLLM's. We only ever raise it (never lower it), since another site owner's own filter
 * may have already set something higher.
 *
 * @param mixed $timeout The current default timeout, in seconds.
 * @return float The (possibly raised) default timeout, in seconds.
 */
function raise_default_request_timeout( $timeout ): float {
	$current = is_numeric( $timeout ) ? (float) $timeout : 0.0;
	return max( $current, Config::requestTimeout() );
}
add_filter( 'wp_ai_client_default_request_timeout', __NAMESPACE__ . '\\raise_default_request_timeout' );

SettingsPage::init();
UpdateChecker::init( __FILE__ );
