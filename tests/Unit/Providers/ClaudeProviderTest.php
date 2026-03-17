<?php
namespace WP_AI_Mind\Tests\Unit\Providers;

use Brain\Monkey;
use Brain\Monkey\Functions;
use WP_AI_Mind\Providers\ClaudeProvider;
use WP_AI_Mind\Providers\CompletionRequest;
use WP_AI_Mind\Providers\ProviderException;
use PHPUnit\Framework\TestCase;

class ClaudeProviderTest extends TestCase {

	protected function setUp(): void    { parent::setUp(); Monkey\setUp(); }
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

	public function test_get_slug_returns_claude(): void {
		$provider = new ClaudeProvider( 'sk-ant-test' );
		$this->assertSame( 'claude', $provider->get_slug() );
	}

	public function test_is_available_false_without_key(): void {
		$provider = new ClaudeProvider( '' );
		$this->assertFalse( $provider->is_available() );
	}

	public function test_is_available_true_with_key(): void {
		$provider = new ClaudeProvider( 'sk-ant-test' );
		$this->assertTrue( $provider->is_available() );
	}

	public function test_complete_parses_response(): void {
		Functions\when( 'wp_remote_post' )->justReturn( [
			'response' => [ 'code' => 200 ],
			'body'     => json_encode( [
				'content' => [ [ 'type' => 'text', 'text' => 'Hello world' ] ],
				'model'   => 'claude-sonnet-4-6',
				'usage'   => [ 'input_tokens' => 10, 'output_tokens' => 5 ],
			] ),
		] );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->alias( fn( $r ) => $r['body'] );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_json_encode' )->alias( fn($v) => json_encode($v) );
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		Functions\when( 'sanitize_key' )->alias( fn($v) => $v );
		Functions\when( 'sanitize_text_field' )->alias( fn($v) => $v );

		global $wpdb;
		$wpdb = new class extends \stdClass {
			public $prefix = 'wpaim_';
			public function insert() {
				return 1;
			}
		};

		$provider = new ClaudeProvider( 'sk-ant-test' );
		$request  = new CompletionRequest( [ [ 'role' => 'user', 'content' => 'hi' ] ] );
		$response = $provider->complete( $request );

		$this->assertSame( 'Hello world', $response->content );
		$this->assertSame( 10, $response->prompt_tokens );
	}

	public function test_complete_throws_on_api_error(): void {
		Functions\when( 'wp_remote_post' )->justReturn( [
			'response' => [ 'code' => 401 ],
			'body'     => json_encode( [ 'error' => [ 'message' => 'Unauthorised' ] ] ),
		] );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 401 );
		Functions\when( 'wp_remote_retrieve_body' )->alias( fn( $r ) => $r['body'] );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_json_encode' )->alias( fn($v) => json_encode($v) );

		$provider = new ClaudeProvider( 'sk-ant-bad-key' );
		$request  = new CompletionRequest( [ [ 'role' => 'user', 'content' => 'hi' ] ] );
		$this->expectException( ProviderException::class );
		$provider->complete( $request );
	}
}
