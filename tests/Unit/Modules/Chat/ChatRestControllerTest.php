<?php
namespace WP_AI_Mind\Tests\Unit\Modules\Chat;

use Brain\Monkey;
use Brain\Monkey\Functions;
use WP_AI_Mind\Modules\Chat\ChatRestController;
use PHPUnit\Framework\TestCase;

class ChatRestControllerTest extends TestCase {

    protected function setUp(): void    { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_register_routes_registers_expected_endpoints(): void {
        $registered = [];
        Functions\when( 'register_rest_route' )->alias(
            function( $ns, $route ) use ( &$registered ) {
                $registered[] = $ns . $route;
            }
        );
        Functions\when( 'get_option' )->justReturn( [] );

        $controller = new ChatRestController();
        $controller->register_routes();

        $this->assertContains( 'wp-ai-mind/v1/conversations', $registered );
        $this->assertContains( 'wp-ai-mind/v1/conversations/(?P<id>\\d+)/messages', $registered );
        $this->assertContains( 'wp-ai-mind/v1/providers', $registered );
    }

    public function test_permission_check_fails_for_non_editors(): void {
        Functions\when( 'current_user_can' )->justReturn( false );
        Functions\when( '__' )->alias( fn( $s ) => $s );
        $controller = new ChatRestController();

        $result = $controller->check_permission();
        $this->assertInstanceOf( \WP_Error::class, $result );
    }
}
