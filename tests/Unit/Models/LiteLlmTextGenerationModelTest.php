<?php
/**
 * Tests for LiteLlmTextGenerationModel.
 *
 * @package WordPress\LiteLLMAiProvider
 */

declare(strict_types=1);

namespace WordPress\LiteLLMAiProvider\Tests\Unit\Models;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\LiteLLMAiProvider\Models\LiteLlmTextGenerationModel;

final class LiteLlmTextGenerationModelTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_create_request_uses_provider_url_and_request_options(): void {
		Functions\expect( 'get_option' )
			->with( 'litellm_api_base', '' )
			->andReturn( 'https://litellm.example.com/v1' );

		$modelMetadata = new ModelMetadata( 'ollama/llama3', 'ollama/llama3', [], [] );
		$providerMetadata = new ProviderMetadata( 'litellm', 'LiteLLM (Ollama)', ProviderTypeEnum::server() );

		$model = new LiteLlmTextGenerationModel( $modelMetadata, $providerMetadata );

		$options = new RequestOptions();
		$options->setTimeout( 30.0 );
		$model->setRequestOptions( $options );

		$ref = new ReflectionMethod( $model, 'createRequest' );
		$request = $ref->invoke(
			$model,
			HttpMethodEnum::POST(),
			'chat/completions',
			[ 'Content-Type' => 'application/json' ],
			[ 'model' => 'ollama/llama3' ]
		);

		$this->assertSame( 'https://litellm.example.com/v1/chat/completions', $request->getUri() );
		$this->assertSame( $options, $request->getOptions() );
	}

	public function test_create_request_extends_execution_time_limit_based_on_configured_timeout(): void {
		Functions\expect( 'get_option' )
			->with( 'litellm_api_base', '' )
			->andReturn( 'https://litellm.example.com/v1' );
		Functions\expect( 'set_time_limit' )
			->once()
			->with( 35 ); // 30s configured timeout + 5s buffer.

		$modelMetadata = new ModelMetadata( 'ollama/llama3', 'ollama/llama3', [], [] );
		$providerMetadata = new ProviderMetadata( 'litellm', 'LiteLLM (Ollama)', ProviderTypeEnum::server() );

		$model = new LiteLlmTextGenerationModel( $modelMetadata, $providerMetadata );

		$options = new RequestOptions();
		$options->setTimeout( 30.0 );
		$model->setRequestOptions( $options );

		$ref = new ReflectionMethod( $model, 'createRequest' );
		$request = $ref->invoke( $model, HttpMethodEnum::POST(), 'chat/completions', [], null );

		$this->assertSame( 'https://litellm.example.com/v1/chat/completions', $request->getUri() );
	}

	public function test_create_request_does_not_extend_execution_time_limit_without_request_options(): void {
		Functions\expect( 'get_option' )
			->with( 'litellm_api_base', '' )
			->andReturn( 'https://litellm.example.com/v1' );
		Functions\expect( 'set_time_limit' )->never();

		$modelMetadata = new ModelMetadata( 'ollama/llama3', 'ollama/llama3', [], [] );
		$providerMetadata = new ProviderMetadata( 'litellm', 'LiteLLM (Ollama)', ProviderTypeEnum::server() );

		$model = new LiteLlmTextGenerationModel( $modelMetadata, $providerMetadata );

		$ref = new ReflectionMethod( $model, 'createRequest' );
		$request = $ref->invoke( $model, HttpMethodEnum::POST(), 'chat/completions', [], null );

		$this->assertSame( 'https://litellm.example.com/v1/chat/completions', $request->getUri() );
	}
}
