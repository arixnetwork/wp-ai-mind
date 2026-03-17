<?php
declare( strict_types=1 );

// Define constants required for Plugin class in test context.
if ( ! defined( 'WP_AI_MIND_BASENAME' ) ) {
    define( 'WP_AI_MIND_BASENAME', 'wp-ai-mind/wp-ai-mind.php' );
}
if ( ! defined( 'WP_AI_MIND_HTTP_TIMEOUT' ) ) {
    define( 'WP_AI_MIND_HTTP_TIMEOUT', 60 );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// WordPress query-result format constants (not provided by Brain Monkey).
if ( ! defined( 'OBJECT' ) )   { define( 'OBJECT',   'OBJECT' ); }
if ( ! defined( 'ARRAY_A' ) )  { define( 'ARRAY_A',  'ARRAY_A' ); }
if ( ! defined( 'ARRAY_N' ) )  { define( 'ARRAY_N',  'ARRAY_N' ); }

// Brain Monkey setUp/tearDown are called per test via trait.
// WP stubs — Brain Monkey provides them when you call Monkey\setUp().

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public string $code;
        public string $message;
        public function __construct( string $code = '', string $message = '', $data = null ) {
            $this->code    = $code;
            $this->message = $message;
        }
    }
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request {
        private array $params = [];
        public function get_param( string $key ) { return $this->params[ $key ] ?? null; }
        public function get_json_params(): array { return $this->params; }
        public function set_body_params( array $params ): void { $this->params = array_merge( $this->params, $params ); }
    }
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        public function __construct( public mixed $data = null, public int $status = 200 ) {}
    }
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
    class WP_REST_Server {
        const READABLE  = 'GET';
        const CREATABLE = 'POST';
        const DELETABLE = 'DELETE';
    }
}

if ( ! function_exists( 'rest_ensure_response' ) ) {
    function rest_ensure_response( $data ) { return new \WP_REST_Response( $data ); }
}
