<?php
declare( strict_types=1 );

namespace WP_AI_Mind\Tests\Unit\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use WP_AI_Mind\Tools\ToolExecutor;
use WP_AI_Mind\Tools\ToolRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Minimal WP_Query stub for the test environment.
 * The real class is not available outside a WordPress bootstrap.
 */
if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public array $posts = [];
		public function __construct( array $args ) {}
	}
}

class ToolExecutorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function make_executor( array $allowed_post_types = [ 'post', 'page' ] ): ToolExecutor {
		$registry = $this->createMock( ToolRegistry::class );
		$registry->method( 'allowed_post_types' )->willReturn( $allowed_post_types );
		return new ToolExecutor( $registry );
	}

	// -------------------------------------------------------------------------
	// Dispatch
	// -------------------------------------------------------------------------

	public function test_execute_unknown_tool_returns_error(): void {
		$executor = $this->make_executor();
		$result   = $executor->execute( 'nonexistent_tool', [], 1 );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'Unknown tool', $result['error'] );
		$this->assertStringContainsString( 'nonexistent_tool', $result['error'] );
	}

	// -------------------------------------------------------------------------
	// get_recent_posts
	// -------------------------------------------------------------------------

	public function test_get_recent_posts_requires_edit_posts_cap(): void {
		Functions\when( 'user_can' )->justReturn( false );

		$executor = $this->make_executor();
		$result   = $executor->execute( 'get_recent_posts', [], 99 );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'permissions', strtolower( $result['error'] ) );
	}

	public function test_get_recent_posts_rejects_disallowed_post_type(): void {
		Functions\when( 'user_can' )->justReturn( true );
		Functions\when( 'sanitize_key' )->alias( static fn( $v ) => $v );

		// Registry allows only 'post', but we pass 'product'.
		$executor = $this->make_executor( [ 'post' ] );
		$result   = $executor->execute( 'get_recent_posts', [ 'post_type' => 'product' ], 1 );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not permitted', $result['error'] );
	}

	// -------------------------------------------------------------------------
	// get_post_content
	// -------------------------------------------------------------------------

	public function test_get_post_content_blocks_private_post_for_other_user(): void {
		Functions\when( 'absint' )->alias( static fn( $v ) => (int) abs( $v ) );

		// Build a minimal post object mimicking WP_Post.
		$post               = new \stdClass();
		$post->ID           = 42;
		$post->post_title   = 'Secret Draft';
		$post->post_content = 'Private content.';
		$post->post_excerpt = '';
		$post->post_status  = 'draft';
		$post->post_author  = '10'; // Author is user 10.
		$post->post_date    = '2026-01-01 12:00:00';

		Functions\when( 'get_post' )->justReturn( $post );

		// user_can: return false for any capability check (not author, not editor).
		Functions\when( 'user_can' )->justReturn( false );

		$executor = $this->make_executor();
		// User 99 is neither the author (10) nor has edit_others_posts.
		$result = $executor->execute( 'get_post_content', [ 'post_id' => 42 ], 99 );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'authorised', strtolower( $result['error'] ) );
	}

	// -------------------------------------------------------------------------
	// create_post
	// -------------------------------------------------------------------------

	public function test_create_post_blocked_when_write_tools_disabled(): void {
		Functions\when( 'get_option' )
			->alias( static function ( string $key, $default = false ) {
				if ( 'wp_ai_mind_enable_write_tools' === $key ) {
					return false;
				}
				return $default;
			} );

		$executor = $this->make_executor();
		$result   = $executor->execute( 'create_post', [ 'title' => 'Test' ], 1 );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'disabled', strtolower( $result['error'] ) );
	}

	// -------------------------------------------------------------------------
	// update_post
	// -------------------------------------------------------------------------

	public function test_update_post_blocked_without_edit_post_cap(): void {
		Functions\when( 'get_option' )
			->alias( static function ( string $key, $default = false ) {
				if ( 'wp_ai_mind_enable_write_tools' === $key ) {
					return true;
				}
				return $default;
			} );

		Functions\when( 'absint' )->alias( static fn( $v ) => (int) abs( $v ) );

		// user_can returns false for edit_post check.
		Functions\when( 'user_can' )->justReturn( false );

		$executor = $this->make_executor();
		$result   = $executor->execute( 'update_post', [ 'post_id' => 5, 'title' => 'New title' ], 1 );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'permissions', strtolower( $result['error'] ) );
	}
}
