<?php
// includes/Modules/Chat/ChatModule.php
declare( strict_types=1 );
namespace WP_AI_Mind\Modules\Chat;

class ChatModule {
    public static function register(): void {
        add_action( 'rest_api_init', function() {
            ( new ChatRestController() )->register_routes();
        } );
    }
}
