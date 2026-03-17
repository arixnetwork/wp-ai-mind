<?php
// includes/Modules/Chat/ChatRestController.php
declare( strict_types=1 );
namespace WP_AI_Mind\Modules\Chat;

use WP_AI_Mind\DB\ConversationStore;
use WP_AI_Mind\Providers\ProviderFactory;
use WP_AI_Mind\Providers\CompletionRequest;
use WP_AI_Mind\Providers\ProviderException;
use WP_AI_Mind\Settings\ProviderSettings;
use WP_AI_Mind\Voice\VoiceInjector;

class ChatRestController {

    private const NAMESPACE = 'wp-ai-mind/v1';

    public function register_routes(): void {

        register_rest_route( self::NAMESPACE, '/conversations', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list_conversations' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_conversation' ],
                'permission_callback' => [ $this, 'check_permission' ],
                'args'                => [
                    'title'   => [ 'type' => 'string', 'default' => '' ],
                    'post_id' => [ 'type' => 'integer', 'default' => 0 ],
                ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/conversations/(?P<id>\d+)/messages', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_messages' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'send_message' ],
                'permission_callback' => [ $this, 'check_permission' ],
                'args'                => [
                    'content'  => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_textarea_field' ],
                    'provider' => [ 'type' => 'string', 'default' => '' ],
                    'model'    => [ 'type' => 'string', 'default' => '' ],
                ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/conversations/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => [ $this, 'delete_conversation' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( self::NAMESPACE, '/providers', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'list_providers' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );
    }

    public function list_conversations( \WP_REST_Request $request ): \WP_REST_Response {
        $store = new ConversationStore();
        return rest_ensure_response( $store->list_for_user( get_current_user_id() ) );
    }

    public function create_conversation( \WP_REST_Request $request ): \WP_REST_Response {
        $store = new ConversationStore();
        $id    = $store->create(
            $request->get_param( 'title' ),
            $request->get_param( 'post_id' ) ?: null
        );
        return rest_ensure_response( [ 'id' => $id ] );
    }

    public function get_messages( \WP_REST_Request $request ): \WP_REST_Response {
        $store = new ConversationStore();
        return rest_ensure_response( $store->get_messages( (int) $request->get_param( 'id' ) ) );
    }

    public function send_message( \WP_REST_Request $request ): \WP_REST_Response {
        $conv_id       = (int) $request->get_param( 'id' );
        $content       = $request->get_param( 'content' );
        $provider_slug = $request->get_param( 'provider' ) ?: get_option( 'wp_ai_mind_default_provider', 'claude' );
        $model         = $request->get_param( 'model' );

        $store = new ConversationStore();
        $store->add_message( $conv_id, 'user', $content );
        $history = $store->get_messages( $conv_id );

        $messages = array_map(
            fn( $m ) => [ 'role' => $m['role'], 'content' => $m['content'] ],
            $history
        );

        $injector = new VoiceInjector();
        $system   = $injector->build_system_prompt( '', get_current_user_id() );

        try {
            $factory  = new ProviderFactory( new ProviderSettings() );
            $provider = $factory->make( $provider_slug );
            $req      = new CompletionRequest(
                messages: $messages,
                system:   $system,
                model:    $model,
                metadata: [ 'feature' => 'chat', 'post_id' => null ],
            );
            $response = $provider->complete( $req );
            $store->add_message( $conv_id, 'assistant', $response->content, $response->model, $response->total_tokens );

            return rest_ensure_response( [
                'content'  => $response->content,
                'model'    => $response->model,
                'tokens'   => $response->total_tokens,
                'cost_usd' => $response->cost_usd,
            ] );
        } catch ( ProviderException $e ) {
            return new \WP_REST_Response( [ 'message' => $e->getMessage() ], 500 );
        }
    }

    public function delete_conversation( \WP_REST_Request $request ): \WP_REST_Response {
        $store   = new ConversationStore();
        $conv_id = (int) $request->get_param( 'id' );
        $conv    = $store->get_conversation( $conv_id );

        if ( ! $conv ) {
            return new \WP_REST_Response( [ 'message' => 'Not found.' ], 404 );
        }
        if ( (int) $conv['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            return new \WP_REST_Response( [ 'message' => 'You cannot delete this conversation.' ], 403 );
        }

        $store->delete( $conv_id );
        return rest_ensure_response( [ 'deleted' => true ] );
    }

    public function list_providers( \WP_REST_Request $request ): \WP_REST_Response {
        $factory   = new ProviderFactory( new ProviderSettings() );
        $available = $factory->get_available();
        $data      = [];
        foreach ( $available as $provider ) {
            $data[] = [
                'slug'   => $provider->get_slug(),
                'models' => $provider->get_models(),
            ];
        }
        return rest_ensure_response( $data );
    }

    public function check_permission(): bool|\WP_Error {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new \WP_Error( 'rest_forbidden', __( 'Insufficient permissions.', 'wp-ai-mind' ), [ 'status' => 403 ] );
        }
        return true;
    }
}
