<?php
/**
 * Tests for Config.
 *
 * @package WordPress\LiteLLMAiProvider
 */

declare(strict_types=1);

namespace WordPress\LiteLLMAiProvider\Tests\Unit\Support;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WordPress\LiteLLMAiProvider\Support\Config;

final class ConfigTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		putenv( 'LITELLM_API_BASE' );
		putenv( 'LITELLM_REQUEST_TIMEOUT' );
	}

	protected function tearDown(): void {
		putenv( 'LITELLM_API_BASE' );
		putenv( 'LITELLM_REQUEST_TIMEOUT' );
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_base_url_prefers_wp_option(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Config::OPTION_BASE_URL, '' )
			->andReturn( 'https://option.example.com/v1/' );

		putenv( 'LITELLM_API_BASE=https://env.example.com/v1' );

		$this->assertSame( 'https://option.example.com/v1', Config::baseUrl() );
	}

	public function test_base_url_falls_back_to_env_var(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Config::OPTION_BASE_URL, '' )
			->andReturn( '' );

		putenv( 'LITELLM_API_BASE=https://env.example.com/v1/' );

		$this->assertSame( 'https://env.example.com/v1', Config::baseUrl() );
	}

	public function test_base_url_falls_back_to_default(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Config::OPTION_BASE_URL, '' )
			->andReturn( '' );

		putenv( 'LITELLM_API_BASE' );

		$this->assertSame( 'http://localhost:4000/v1', Config::baseUrl() );
	}

	public function test_default_model_reads_option(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Config::OPTION_DEFAULT_MODEL, '' )
			->andReturn( 'ollama/llama3' );

		$this->assertSame( 'ollama/llama3', Config::defaultModel() );
	}

	public function test_request_timeout_prefers_wp_option(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Config::OPTION_REQUEST_TIMEOUT, '' )
			->andReturn( '45' );

		putenv( 'LITELLM_REQUEST_TIMEOUT=90' );

		$this->assertSame( 45.0, Config::requestTimeout() );
	}

	public function test_request_timeout_falls_back_to_env_var(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Config::OPTION_REQUEST_TIMEOUT, '' )
			->andReturn( '' );

		putenv( 'LITELLM_REQUEST_TIMEOUT=90' );

		$this->assertSame( 90.0, Config::requestTimeout() );
	}

	public function test_request_timeout_falls_back_to_default(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Config::OPTION_REQUEST_TIMEOUT, '' )
			->andReturn( '' );

		putenv( 'LITELLM_REQUEST_TIMEOUT' );

		$this->assertSame( 30.0, Config::requestTimeout() );
	}

	public function test_request_timeout_rejects_non_positive_and_non_numeric_option(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Config::OPTION_REQUEST_TIMEOUT, '' )
			->andReturn( '-5' );

		putenv( 'LITELLM_REQUEST_TIMEOUT=90' );

		$this->assertSame( 90.0, Config::requestTimeout() );
	}
}
