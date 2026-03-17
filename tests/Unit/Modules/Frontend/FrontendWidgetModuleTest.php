<?php
declare( strict_types=1 );

namespace WP_AI_Mind\Tests\Unit\Modules\Frontend;

use Brain\Monkey;
use Brain\Monkey\Functions;
use WP_AI_Mind\Modules\Frontend\FrontendWidgetModule;
use PHPUnit\Framework\TestCase;

class FrontendWidgetModuleTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_hooks_wp_enqueue_scripts(): void {
        Functions\when( 'add_shortcode' )->justReturn( null );
        FrontendWidgetModule::register();
        self::assertSame(
            10,
            has_action( 'wp_enqueue_scripts', [ FrontendWidgetModule::class, 'enqueue_assets' ] )
        );
    }

    public function test_render_shortcode_outputs_div(): void {
        Functions\when( 'shortcode_atts' )->returnArg( 1 );
        Functions\when( 'get_the_ID' )->justReturn( 42 );
        Functions\when( 'absint' )->alias( fn( $v ) => abs( (int) $v ) );
        Functions\when( 'esc_attr' )->returnArg();

        $output = FrontendWidgetModule::render_shortcode( [] );

        self::assertStringContainsString( 'wp-ai-mind-widget', $output );
        self::assertStringContainsString( 'data-post-id="42"', $output );
    }
}
