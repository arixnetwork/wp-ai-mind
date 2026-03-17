<?php
declare( strict_types=1 );

namespace WP_AI_Mind\Tests\Unit\DB;

use Brain\Monkey;
use Brain\Monkey\Functions;
use WP_AI_Mind\DB\Schema;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase {

    protected function setUp(): void    { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_get_usage_log_table_name(): void {
        global $wpdb;
        $wpdb         = new \stdClass();
        $wpdb->prefix = 'wp_';
        $this->assertSame( 'wp_wpaim_usage_log', Schema::table( 'usage_log' ) );
    }

    public function test_table_name_with_custom_prefix(): void {
        global $wpdb;
        $wpdb         = new \stdClass();
        $wpdb->prefix = 'mysite_';
        $this->assertSame( 'mysite_wpaim_usage_log', Schema::table( 'usage_log' ) );
    }

    public function test_unknown_table_throws_exception(): void {
        global $wpdb;
        $wpdb         = new \stdClass();
        $wpdb->prefix = 'wp_';
        $this->expectException( \InvalidArgumentException::class );
        Schema::table( 'nonexistent_table' );
    }
}
