<?php
/**
 * Tests for SettingsPage.
 *
 * @package WordPress\LiteLLMAiProvider
 */

declare(strict_types=1);

namespace WordPress\LiteLLMAiProvider\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WordPress\LiteLLMAiProvider\Admin\SettingsPage;
use WordPress\LiteLLMAiProvider\Support\Config;

final class SettingsPageTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'untrailingslashit' )->alias(
			static fn( string $s ): string => rtrim( $s, '/' )
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_valid_url_is_accepted_and_trailing_slash_removed(): void {
		Functions\when( 'wp_http_validate_url' )->justReturn( 'https://litellm.example.com/v1/' );

		$result = SettingsPage::sanitize_base_url( 'https://litellm.example.com/v1/' );

		$this->assertSame( 'https://litellm.example.com/v1', $result );
	}

	public function test_invalid_url_is_rejected_and_falls_back_to_existing_option(): void {
		Functions\when( 'wp_http_validate_url' )->justReturn( false );
		Functions\expect( 'add_settings_error' )
			->once()
			->with( Config::OPTION_BASE_URL, 'litellm_invalid_base_url', \Mockery::type( 'string' ) );
		Functions\when( 'get_option' )->justReturn( 'https://existing.example.com/v1' );

		$result = SettingsPage::sanitize_base_url( 'not a url' );

		$this->assertSame( 'https://existing.example.com/v1', $result );
	}

	public function test_empty_value_is_accepted_as_is(): void {
		$result = SettingsPage::sanitize_base_url( '' );

		$this->assertSame( '', $result );
	}

	public function test_non_string_value_is_treated_as_empty(): void {
		$result = SettingsPage::sanitize_base_url( null );

		$this->assertSame( '', $result );
	}
}
