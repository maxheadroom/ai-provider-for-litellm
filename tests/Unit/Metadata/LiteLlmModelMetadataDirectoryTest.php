<?php
/**
 * Tests for LiteLlmModelMetadataDirectory.
 *
 * @package WordPress\LiteLLMAiProvider
 */

declare(strict_types=1);

namespace WordPress\LiteLLMAiProvider\Tests\Unit\Metadata;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\LiteLLMAiProvider\Metadata\LiteLlmModelMetadataDirectory;
use WordPress\LiteLLMAiProvider\Support\Config;

final class LiteLlmModelMetadataDirectoryTest extends TestCase {

	private LiteLlmModelMetadataDirectory $directory;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->directory = new LiteLlmModelMetadataDirectory();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_parses_text_generation_models(): void {
		$response = $this->jsonResponse(
			[
				'data' => [
					[ 'id' => 'ollama/llama3' ],
				],
			]
		);

		$models = $this->parse( $response );

		$this->assertCount( 1, $models );
		$this->assertSame( 'ollama/llama3', $models[0]->getId() );
		$this->assertCapabilities(
			[ CapabilityEnum::TEXT_GENERATION, CapabilityEnum::CHAT_HISTORY ],
			$models[0]->getSupportedCapabilities()
		);
	}

	public function test_classifies_embedding_models_by_id_substring(): void {
		$response = $this->jsonResponse(
			[
				'data' => [
					[ 'id' => 'ollama/nomic-embed-text' ],
					[ 'id' => 'EMBEDDING-model' ],
				],
			]
		);

		$models = $this->parse( $response );

		$this->assertCount( 2, $models );
		foreach ( $models as $model ) {
			$this->assertCapabilities( [ CapabilityEnum::EMBEDDING_GENERATION ], $model->getSupportedCapabilities() );
		}
	}

	public function test_skips_entries_without_a_valid_id(): void {
		$response = $this->jsonResponse(
			[
				'data' => [
					[ 'id' => 'valid-model' ],
					[ 'id' => '' ],
					[ 'not_id' => 'nope' ],
					'not-even-an-array',
				],
			]
		);

		$models = $this->parse( $response );

		$this->assertCount( 1, $models );
		$this->assertSame( 'valid-model', $models[0]->getId() );
	}

	public function test_returns_empty_list_when_data_key_is_missing(): void {
		$response = $this->jsonResponse( [] );

		$this->assertSame( [], $this->parse( $response ) );
	}

	public function test_create_request_builds_url_from_provider(): void {
		Functions\expect( 'get_option' )
			->with( 'litellm_api_base', '' )
			->andReturn( 'https://litellm.example.com/v1' );

		$ref = new ReflectionMethod( $this->directory, 'createRequest' );
		$request = $ref->invoke( $this->directory, HttpMethodEnum::GET(), 'models', [], null );

		$this->assertSame( 'https://litellm.example.com/v1/models', $request->getUri() );
	}

	public function test_options_for_vision_capable_model_includes_image_input_combination(): void {
		$this->setVisionCapableModelIds( [ 'ollama/llava' => true ] );

		$response = $this->jsonResponse(
			[
				'data' => [
					[ 'id' => 'ollama/llava' ],
					[ 'id' => 'ollama/llama3' ],
				],
			]
		);

		$models = $this->parse( $response );

		$this->assertInputModalitySets(
			[ [ 'text' ], [ 'text', 'image' ] ],
			$this->findModel( $models, 'ollama/llava' )
		);
		$this->assertInputModalitySets(
			[ [ 'text' ] ],
			$this->findModel( $models, 'ollama/llama3' )
		);
	}

	public function test_fetch_model_info_vision_flags_parses_supports_vision(): void {
		Functions\expect( 'get_option' )
			->with( 'litellm_api_base', '' )
			->andReturn( 'https://litellm.example.com/v1' );

		$this->directory->setRequestAuthentication( new PassthroughRequestAuthentication() );
		$this->directory->setHttpTransporter(
			new StubHttpTransporter(
				new Response(
					200,
					[],
					(string) json_encode(
						[
							'data' => [
								[
									'model_name' => 'ollama/llava',
									'model_info' => [ 'supports_vision' => true ],
								],
								[
									'model_name' => 'ollama/llama3',
									'model_info' => [ 'supports_vision' => false ],
								],
								[
									'model_name' => 'ollama/mistral',
									'model_info' => [ 'supports_vision' => null ],
								],
								[
									'model_name' => 'no-model-info',
								],
							],
						]
					)
				)
			)
		);

		$ref = new ReflectionMethod( $this->directory, 'fetchModelInfoVisionFlags' );
		$flags = $ref->invoke( $this->directory );

		$this->assertSame(
			[
				'ollama/llava'   => true,
				'ollama/llama3'  => false,
				'ollama/mistral' => false,
				'no-model-info'  => false,
			],
			$flags
		);
	}

	public function test_fetch_model_info_vision_flags_returns_empty_on_unsuccessful_response(): void {
		Functions\expect( 'get_option' )
			->with( 'litellm_api_base', '' )
			->andReturn( 'https://litellm.example.com/v1' );

		$this->directory->setRequestAuthentication( new PassthroughRequestAuthentication() );
		$this->directory->setHttpTransporter( new StubHttpTransporter( new Response( 404, [], '' ) ) );

		$ref = new ReflectionMethod( $this->directory, 'fetchModelInfoVisionFlags' );
		$this->assertSame( [], $ref->invoke( $this->directory ) );
	}

	public function test_fetch_model_info_vision_flags_returns_empty_when_transporter_throws(): void {
		Functions\expect( 'get_option' )
			->with( 'litellm_api_base', '' )
			->andReturn( 'https://litellm.example.com/v1' );

		$this->directory->setRequestAuthentication( new PassthroughRequestAuthentication() );
		$this->directory->setHttpTransporter( new ThrowingHttpTransporter() );

		$ref = new ReflectionMethod( $this->directory, 'fetchModelInfoVisionFlags' );
		$this->assertSame( [], $ref->invoke( $this->directory ) );
	}

	public function test_resolve_vision_capable_model_ids_unions_config_override_and_model_info(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $option, $default = false ) {
				if ( 'litellm_api_base' === $option ) {
					return 'https://litellm.example.com/v1';
				}
				if ( Config::OPTION_VISION_MODELS === $option ) {
					return 'manually-overridden-model';
				}
				return $default;
			}
		);

		$this->directory->setRequestAuthentication( new PassthroughRequestAuthentication() );
		$this->directory->setHttpTransporter(
			new StubHttpTransporter(
				new Response(
					200,
					[],
					(string) json_encode(
						[
							'data' => [
								[
									'model_name' => 'auto-detected-vision-model',
									'model_info' => [ 'supports_vision' => true ],
								],
							],
						]
					)
				)
			)
		);

		$ref = new ReflectionMethod( $this->directory, 'resolveVisionCapableModelIds' );
		$ids = $ref->invoke( $this->directory );

		$this->assertSame(
			[
				'manually-overridden-model'  => true,
				'auto-detected-vision-model' => true,
			],
			$ids
		);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function jsonResponse( array $data ): Response {
		return new Response( 200, [], (string) json_encode( $data ) );
	}

	/**
	 * @return list<ModelMetadata>
	 */
	private function parse( Response $response ): array {
		$ref = new ReflectionMethod( $this->directory, 'parseResponseToModelMetadataList' );
		return $ref->invoke( $this->directory, $response );
	}

	/**
	 * @param list<string> $expected
	 * @param list<CapabilityEnum> $actual
	 */
	private function assertCapabilities( array $expected, array $actual ): void {
		$this->assertSame( $expected, array_map( static fn( CapabilityEnum $c ) => $c->value, $actual ) );
	}

	/**
	 * @param array<string, true> $ids
	 */
	private function setVisionCapableModelIds( array $ids ): void {
		$prop = new ReflectionProperty( $this->directory, 'visionCapableModelIds' );
		$prop->setValue( $this->directory, $ids );
	}

	/**
	 * @param list<ModelMetadata> $models
	 */
	private function findModel( array $models, string $id ): ModelMetadata {
		foreach ( $models as $model ) {
			if ( $model->getId() === $id ) {
				return $model;
			}
		}
		$this->fail( sprintf( 'No model with ID "%s" found.', $id ) );
	}

	/**
	 * @param list<list<string>> $expectedSets
	 */
	private function assertInputModalitySets( array $expectedSets, ModelMetadata $model ): void {
		foreach ( $model->getSupportedOptions() as $option ) {
			if ( ! $option->getName()->isInputModalities() ) {
				continue;
			}

			$actualSets = array_map(
				static function ( array $set ): array {
					$values = array_map( static fn( $modality ) => $modality->value, $set );
					sort( $values );
					return $values;
				},
				$option->getSupportedValues() ?? []
			);
			$expectedSorted = array_map(
				static function ( array $set ): array {
					sort( $set );
					return $set;
				},
				$expectedSets
			);

			sort( $actualSets );
			sort( $expectedSorted );

			$this->assertSame( $expectedSorted, $actualSets );
			return;
		}

		$this->fail( 'No inputModalities option found on model.' );
	}
}

/**
 * Test double: passes requests through unauthenticated.
 */
final class PassthroughRequestAuthentication implements RequestAuthenticationInterface {
	public function authenticateRequest( Request $request ): Request {
		return $request;
	}

	public static function getJsonSchema(): array {
		return [];
	}
}

/**
 * Test double: returns a canned response regardless of the request sent.
 */
final class StubHttpTransporter implements HttpTransporterInterface {
	public function __construct( private Response $response ) {
	}

	public function send( Request $request, ?RequestOptions $options = null ): Response {
		return $this->response;
	}
}

/**
 * Test double: simulates a transport-level failure.
 */
final class ThrowingHttpTransporter implements HttpTransporterInterface {
	public function send( Request $request, ?RequestOptions $options = null ): Response {
		throw new RuntimeException( 'Simulated network failure.' );
	}
}
