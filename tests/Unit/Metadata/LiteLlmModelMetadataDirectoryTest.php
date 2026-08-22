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
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\LiteLLMAiProvider\Metadata\LiteLlmModelMetadataDirectory;

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

	/**
	 * @param array<string, mixed> $data
	 */
	private function jsonResponse( array $data ): Response {
		return new Response( 200, [], (string) json_encode( $data ) );
	}

	/**
	 * @return list<\WordPress\AiClient\Providers\Models\DTO\ModelMetadata>
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
}
