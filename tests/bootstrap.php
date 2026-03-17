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

// Brain Monkey setUp/tearDown are called per test via trait.
// WP stubs — Brain Monkey provides them when you call Monkey\setUp().
