<?php
/**
 * Plugin Name:       AI Provider for LiteLLM
 * Description:       Registers an AI Client provider that talks to a LiteLLM gateway (e.g. backed by Ollama) using the OpenAI-compatible wire protocol.
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Version:           0.1.0
 * Author:            LiteLLM AI Provider Contributors
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

SettingsPage::init();
