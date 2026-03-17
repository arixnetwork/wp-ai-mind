<?php
// includes/Modules/Chat/ChatModule.php
declare( strict_types=1 );
namespace WP_AI_Mind\Modules\Chat;

use WP_AI_Mind\Tools\ToolRegistry;
use WP_AI_Mind\Tools\ToolExecutor;

class ChatModule {
    public static function register(): void {
        add_action( 'rest_api_init', function() {
            $tool_registry = new ToolRegistry();
            $tool_executor = new ToolExecutor( $tool_registry );
            ( new ChatRestController( $tool_registry, $tool_executor ) )->register_routes();
            ( new SettingsRestController() )->register_routes();
        } );
    }
}
