<?php
// includes/Modules/Chat/ChatRestController.php
declare( strict_types=1 );
namespace WP_AI_Mind\Modules\Chat;

use WP_AI_Mind\DB\ConversationStore;
use WP_AI_Mind\Providers\ProviderFactory;
use WP_AI_Mind\Providers\CompletionRequest;
use WP_AI_Mind\Providers\CompletionResponse;
use WP_AI_Mind\Providers\ProviderException;
use WP_AI_Mind\Settings\ProviderSettings;
use WP_AI_Mind\Tools\ToolRegistry;
use WP_AI_Mind\Tools\ToolExecutor;
use WP_AI_Mind\Voice\VoiceInjector;

class ChatRestController {

    private const NAMESPACE = 'wp-ai-mind/v1';

    public function __construct(
        private readonly ToolRegistry $tool_registry,
        private readonly ToolExecutor $tool_executor,
    ) {}

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
        $store = $this->make_store();
        return rest_ensure_response( $store->list_for_user( get_current_user_id() ) );
    }

    public function create_conversation( \WP_REST_Request $request ): \WP_REST_Response {
        $store = $this->make_store();
        $id    = $store->create(
            $request->get_param( 'title' ),
            $request->get_param( 'post_id' ) ?: null
        );
        return rest_ensure_response( [ 'id' => $id ] );
    }

    public function get_messages( \WP_REST_Request $request ): \WP_REST_Response {
        $store = $this->make_store();
        return rest_ensure_response( $store->get_messages( (int) $request->get_param( 'id' ) ) );
    }

    public function send_message( \WP_REST_Request $request ): \WP_REST_Response {
        $conv_id       = (int) $request->get_param( 'id' );
        $content       = $request->get_param( 'content' );
        $provider_slug = $request->get_param( 'provider' ) ?: \get_option( 'wp_ai_mind_default_provider', 'claude' );
        $model         = $request->get_param( 'model' );

        $store = $this->make_store();

        // Ownership guard.
        $conv = $store->get_conversation( $conv_id );
        if ( ! $conv || (int) $conv['user_id'] !== \get_current_user_id() ) {
            return new \WP_REST_Response( [ 'message' => 'Forbidden.' ], 403 );
        }

        $store->add_message( $conv_id, 'user', $content );
        $history = $store->get_messages( $conv_id );

        $messages = array_map(
            fn( $m ) => [ 'role' => $m['role'], 'content' => $m['content'] ],
            $history
        );

        $injector = $this->make_voice_injector();
        $system   = $injector->build_system_prompt( '', \get_current_user_id() );

        try {
            $factory  = $this->make_provider_factory();
            $provider = $factory->make( $provider_slug );

            $tools = $provider->supports_tools()
                ? $this->tool_registry->get_for_provider( $provider_slug )
                : [];

            $max_iterations = 5;
            $iteration      = 0;
            $final_response = null;

            while ( $iteration < $max_iterations ) {
                $iteration++;

                $req = new CompletionRequest(
                    messages:    $messages,
                    system:      $system,
                    model:       $model,
                    metadata:    [ 'feature' => 'chat', 'post_id' => null ],
                    tools:       $tools,
                );

                $response = $provider->complete( $req );

                if ( \is_wp_error( $response ) ) {
                    return new \WP_REST_Response(
                        [ 'message' => $response->get_error_message() ],
                        502
                    );
                }

                if ( ! $response->is_tool_call() ) {
                    $final_response = $response;
                    break;
                }

                $tool_call   = $response->tool_call;
                $tool_result = $this->tool_executor->execute(
                    $tool_call['name'],
                    $tool_call['arguments'],
                    \get_current_user_id()
                );

                $messages = $this->append_tool_exchange( $messages, $provider_slug, $response, $tool_result );
            }

            if ( null === $final_response ) {
                return new \WP_REST_Response(
                    [ 'message' => 'Tool call limit reached without a final response.' ],
                    500
                );
            }

            $store->add_message( $conv_id, 'assistant', $final_response->content, $final_response->model, $final_response->total_tokens );

            return rest_ensure_response( [
                'content'  => $final_response->content,
                'model'    => $final_response->model,
                'tokens'   => $final_response->total_tokens,
                'cost_usd' => $final_response->cost_usd,
            ] );
        } catch ( ProviderException $e ) {
            $status = $e->get_http_status() >= 400 && $e->get_http_status() < 600 ? $e->get_http_status() : 502;
            return new \WP_REST_Response( [ 'message' => $e->getMessage() ], $status );
        }
    }

    public function delete_conversation( \WP_REST_Request $request ): \WP_REST_Response {
        $store   = $this->make_store();
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
        $factory   = $this->make_provider_factory();
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

    // ── Overridable factory methods (for testing) ─────────────────────────────

    protected function make_store(): ConversationStore {
        return new ConversationStore();
    }

    protected function make_provider_factory(): ProviderFactory {
        return new ProviderFactory( new ProviderSettings() );
    }

    protected function make_voice_injector(): VoiceInjector {
        return new VoiceInjector();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function append_tool_exchange(
        array $messages,
        string $provider_slug,
        CompletionResponse $tool_response,
        array $tool_result
    ): array {
        $tool_call   = $tool_response->tool_call;
        $result_json = \wp_json_encode( $tool_result );

        switch ( $provider_slug ) {
            case 'claude':
                $messages[] = [
                    'role'    => 'assistant',
                    'content' => $tool_response->raw['content'] ?? [],
                ];
                $messages[] = [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'        => 'tool_result',
                            'tool_use_id' => $tool_call['id'],
                            'content'     => $result_json,
                        ],
                    ],
                ];
                break;

            case 'openai':
            case 'grok':
                $messages[] = [
                    'role'       => 'assistant',
                    'tool_calls' => [
                        [
                            'id'       => $tool_call['id'],
                            'type'     => 'function',
                            'function' => [
                                'name'      => $tool_call['name'],
                                'arguments' => \wp_json_encode( $tool_call['arguments'] ),
                            ],
                        ],
                    ],
                ];
                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $tool_call['id'],
                    'content'      => $result_json,
                ];
                break;

            case 'gemini':
                $call_id    = $tool_response->raw['call_id'] ?? $tool_call['id'];
                $messages[] = [
                    'role'  => 'model',
                    'parts' => [
                        [
                            'functionCall' => [
                                'id'   => $call_id,
                                'name' => $tool_call['name'],
                                'args' => $tool_call['arguments'],
                            ],
                        ],
                    ],
                ];
                $messages[] = [
                    'role'  => 'user',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'id'       => $call_id,
                                'name'     => $tool_call['name'],
                                'response' => $tool_result,
                            ],
                        ],
                    ],
                ];
                break;

            default:
                $messages[] = [
                    'role'    => 'user',
                    'content' => 'Tool result: ' . $result_json,
                ];
        }

        return $messages;
    }
}
