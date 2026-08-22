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
	}

	protected function tearDown(): void {
		putenv( 'LITELLM_API_BASE' );
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
}
