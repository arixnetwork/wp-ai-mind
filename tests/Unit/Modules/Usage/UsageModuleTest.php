<?php
declare( strict_types=1 );

namespace WP_AI_Mind\Tests\Unit\Modules\Usage;

use Brain\Monkey;
use WP_AI_Mind\Modules\Usage\UsageModule;
use PHPUnit\Framework\TestCase;

/**
 * Minimal wpdb stub that supports prepare() and get_results().
 */
class FakeWpdbForUsage {
	public string $prefix = 'wp_';

	public function prepare( string $query, ...$args ): string {
		// Replace %d placeholders with the supplied integer values.
		return vsprintf( str_replace( '%d', '%d', $query ), $args );
	}

	public function get_results( string $query, $output = \OBJECT ): array {
		// Return a deterministic fake row on every call.
		return [
			[
				'provider' => 'openai',
				'feature'  => 'chat',
				'tokens'   => '1500',
				'cost'     => '0.003000',
				'requests' => '5',
				'day'      => '2026-03-17',
			],
		];
	}
}

class UsageModuleTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_register_hooks_admin_enqueue_scripts(): void {
		UsageModule::register();
		self::assertSame(
			10,
			has_action( 'admin_enqueue_scripts', [ UsageModule::class, 'enqueue_assets' ] )
		);
	}

	public function test_register_hooks_rest_api_init(): void {
		UsageModule::register();
		self::assertSame(
			10,
			has_action( 'rest_api_init', [ UsageModule::class, 'register_routes' ] )
		);
	}

	public function test_get_usage_returns_expected_shape(): void {
		global $wpdb;
		$original_wpdb = $wpdb;
		$wpdb          = new FakeWpdbForUsage();

		$request  = new \WP_REST_Request();
		$response = UsageModule::get_usage( $request );

		$wpdb = $original_wpdb;

		$data = $response->data;

		self::assertArrayHasKey( 'breakdown',   $data );
		self::assertArrayHasKey( 'daily',       $data );
		self::assertArrayHasKey( 'totals',      $data );
		self::assertArrayHasKey( 'period_days', $data );
		self::assertSame( 30, $data['period_days'] );
		self::assertArrayHasKey( 'tokens',   $data['totals'] );
		self::assertArrayHasKey( 'cost_usd', $data['totals'] );
		self::assertArrayHasKey( 'requests', $data['totals'] );
	}
}
