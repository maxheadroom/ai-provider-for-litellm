<?php
/**
 * LiteLLM provider settings page.
 *
 * @package WordPress\LiteLLMAiProvider
 */

declare(strict_types=1);

namespace WordPress\LiteLLMAiProvider\Admin;

use WordPress\LiteLLMAiProvider\Support\Config;

/**
 * Renders the "Settings → LiteLLM Provider" admin page.
 *
 * Only manages the LiteLLM base URL and default model. The API key is
 * intentionally NOT managed here: WordPress core's Connectors API (WP 7.0+)
 * already auto-derives a connector entry for this provider (via its
 * ProviderMetadata) and owns API-key storage, masking, and validation on the
 * Settings → Connectors screen. Duplicating that here would create two
 * sources of truth for the same secret.
 */
final class SettingsPage {

	private const PAGE_SLUG = 'litellm-provider';

	private const OPTION_GROUP = 'litellm_provider_settings';

	private const NONCE_ACTION = 'litellm_test_connection';

	/**
	 * Registers all WordPress hooks for this settings page.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register_menu' ] );
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
		add_action( 'wp_ajax_litellm_test_connection', [ self::class, 'ajax_test_connection' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	/**
	 * Adds the "LiteLLM Provider" submenu page under Settings.
	 */
	public static function register_menu(): void {
		add_options_page(
			__( 'LiteLLM Provider', 'ai-provider-for-litellm' ),
			__( 'LiteLLM Provider', 'ai-provider-for-litellm' ),
			'manage_options',
			self::PAGE_SLUG,
			[ self::class, 'render' ]
		);
	}

	/**
	 * Registers the plugin's settings, sections, and fields.
	 */
	public static function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			Config::OPTION_BASE_URL,
			[
				'type'              => 'string',
				'sanitize_callback' => [ self::class, 'sanitize_base_url' ],
				'default'           => '',
			]
		);

		register_setting(
			self::OPTION_GROUP,
			Config::OPTION_DEFAULT_MODEL,
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			]
		);

		add_settings_section(
			'litellm_provider_main',
			'',
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			Config::OPTION_BASE_URL,
			__( 'LiteLLM API Base URL', 'ai-provider-for-litellm' ),
			[ self::class, 'render_base_url_field' ],
			self::PAGE_SLUG,
			'litellm_provider_main'
		);

		add_settings_field(
			Config::OPTION_DEFAULT_MODEL,
			__( 'Default Model', 'ai-provider-for-litellm' ),
			[ self::class, 'render_default_model_field' ],
			self::PAGE_SLUG,
			'litellm_provider_main'
		);
	}

	/**
	 * Sanitizes the submitted base URL, rejecting invalid URLs.
	 *
	 * @param mixed $value Raw submitted value.
	 */
	public static function sanitize_base_url( $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value ) {
			return '';
		}

		$validated = wp_http_validate_url( $value );

		if ( ! $validated ) {
			add_settings_error(
				Config::OPTION_BASE_URL,
				'litellm_invalid_base_url',
				__( 'The LiteLLM API Base URL must be a valid, reachable-looking HTTP(S) URL.', 'ai-provider-for-litellm' )
			);
			return (string) get_option( Config::OPTION_BASE_URL, '' );
		}

		return untrailingslashit( esc_url_raw( $value ) );
	}

	/**
	 * Renders the base URL field, including the "Test Connection" button.
	 */
	public static function render_base_url_field(): void {
		$value = get_option( Config::OPTION_BASE_URL, '' );
		?>
		<input
			type="url"
			id="litellm_api_base"
			name="<?php echo esc_attr( Config::OPTION_BASE_URL ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="<?php echo esc_attr( Config::DEFAULT_BASE_URL ); ?>"
		/>
		<button type="button" class="button" id="litellm-test-connection">
			<?php esc_html_e( 'Test Connection', 'ai-provider-for-litellm' ); ?>
		</button>
		<p class="description">
			<?php esc_html_e( 'Leave blank to use the LITELLM_API_BASE environment variable, or the default http://localhost:4000/v1.', 'ai-provider-for-litellm' ); ?>
		</p>
		<p id="litellm-test-connection-result"></p>
		<?php
	}

	/**
	 * Renders the default model field.
	 */
	public static function render_default_model_field(): void {
		$value = get_option( Config::OPTION_DEFAULT_MODEL, '' );
		?>
		<input
			type="text"
			id="litellm_default_model"
			name="<?php echo esc_attr( Config::OPTION_DEFAULT_MODEL ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="ollama/llama3"
		/>
		<?php
	}

	/**
	 * Renders the settings page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'LiteLLM Provider', 'ai-provider-for-litellm' ); ?></h1>
			<p>
				<?php esc_html_e( 'Configure the LiteLLM gateway this site connects to. The API key is managed on the Settings → Connectors screen.', 'ai-provider-for-litellm' ); ?>
			</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueues the "Test Connection" button's inline script on this settings page only.
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_add_inline_script(
			'jquery',
			self::get_test_connection_script(),
			'after'
		);
	}

	/**
	 * Builds the "Test Connection" button's inline JS.
	 */
	private static function get_test_connection_script(): string {
		$nonce  = wp_create_nonce( self::NONCE_ACTION );
		$ajax   = admin_url( 'admin-ajax.php' );
		$action = 'litellm_test_connection';

		return <<<JS
jQuery(function ($) {
	$('#litellm-test-connection').on('click', function () {
		var \$button = $(this);
		var \$result = $('#litellm-test-connection-result');
		\$button.prop('disabled', true);
		\$result.text('Testing…');
		$.post('{$ajax}', {
			action: '{$action}',
			nonce: '{$nonce}',
			base_url: $('#litellm_api_base').val()
		}).done(function (response) {
			\$result.text(response && response.data && response.data.message ? response.data.message : 'Unknown response.');
		}).fail(function () {
			\$result.text('Request failed.');
		}).always(function () {
			\$button.prop('disabled', false);
		});
	});
});
JS;
	}

	/**
	 * AJAX handler for the "Test Connection" button.
	 *
	 * Performs a plain, unauthenticated GET against `{base_url}/models` to
	 * validate reachability and URL correctness only. It deliberately does
	 * not test the API key (that's WP core's Connectors screen's job) or
	 * route through the AI Client SDK's authenticated/cached pipeline.
	 */
	public static function ajax_test_connection(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ai-provider-for-litellm' ) ], 403 );
		}

		$submitted = isset( $_POST['base_url'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['base_url'] ) ) : '';
		$base_url  = '' !== $submitted ? untrailingslashit( $submitted ) : Config::baseUrl();

		if ( ! wp_http_validate_url( $base_url ) ) {
			wp_send_json_error( [ 'message' => __( 'That does not look like a valid URL.', 'ai-provider-for-litellm' ) ] );
		}

		$response = wp_remote_get( $base_url . '/models', [ 'timeout' => 10 ] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [ 'message' => $response->get_error_message() ] );
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			wp_send_json_success( [ 'message' => __( 'Reachable — LiteLLM responded successfully.', 'ai-provider-for-litellm' ) ] );
		}

		if ( 401 === $code ) {
			wp_send_json_success(
				[
					'message' => __( 'Reachable — LiteLLM requires an API key. Set it on the Settings → Connectors screen.', 'ai-provider-for-litellm' ),
				]
			);
		}

		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$message = is_array( $body ) && isset( $body['error']['message'] ) && is_string( $body['error']['message'] )
			? $body['error']['message']
			/* translators: %d: HTTP status code. */
			: sprintf( __( 'HTTP %d from LiteLLM.', 'ai-provider-for-litellm' ), $code );

		wp_send_json_error( [ 'message' => $message ] );
	}
}
