<?php
/**
 * Self-hosted update checks via a JSON metadata file.
 *
 * @package WordPress\LiteLLMAiProvider
 */

declare(strict_types=1);

namespace WordPress\LiteLLMAiProvider\Support;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Wires up Plugin Update Checker (bundled under lib/plugin-update-checker/) so
 * sites installing this plugin outside the WordPress.org directory -- e.g. via
 * a direct ZIP download -- still get update notices in wp-admin.
 *
 * Deliberately uses PUC's plain JSON-metadata mode rather than its GitHub/
 * GitLab/BitBucket VCS integrations: those are hardcoded to each service's own
 * API (GitHubApi.php, for instance, always talks to api.github.com, with no
 * way to point it at a self-hosted forge such as Gitea/Forgejo -- and no such
 * API class exists for those anyway). A plain metadata URL has no opinion
 * about what serves it, so this plugin's distribution host can change (GitHub
 * today, a private Forgejo instance tomorrow) without touching this file --
 * only `update.json` needs to move.
 */
final class UpdateChecker {

	/**
	 * URL of the update metadata JSON file. Must be updated by hand alongside
	 * `update.json` itself if the file's hosting location ever changes.
	 */
	private const METADATA_URL = 'https://raw.githubusercontent.com/maxheadroom/ai-provider-for-litellm/main/update.json';

	/**
	 * Registers the update checker against this plugin's main file.
	 *
	 * A missing bundled library (e.g. a source checkout that never ran the
	 * release build) must not break the rest of the plugin, so this degrades
	 * to a no-op rather than fataling.
	 *
	 * @param string $pluginFile Absolute path to this plugin's main file.
	 */
	public static function init( string $pluginFile ): void {
		$bootstrap = __DIR__ . '/../../lib/plugin-update-checker/plugin-update-checker.php';

		if ( ! is_readable( $bootstrap ) ) {
			return;
		}

		require_once $bootstrap;

		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		PucFactory::buildUpdateChecker(
			self::METADATA_URL,
			$pluginFile,
			'ai-provider-for-litellm'
		);
	}
}
