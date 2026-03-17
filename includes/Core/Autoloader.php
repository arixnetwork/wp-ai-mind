<?php
declare( strict_types=1 );
namespace WP_AI_Mind\Core;
class Autoloader {
    private static bool $registered = false;
    public static function register(): bool {
        if ( self::$registered ) { return true; }
        self::$registered = spl_autoload_register( [ self::class, 'load' ] );
        return (bool) self::$registered;
    }
    public static function load( string $class ): void {
        $prefix = 'WP_AI_Mind\\';
        if ( ! str_starts_with( $class, $prefix ) ) { return; }
        $relative = substr( $class, strlen( $prefix ) );
        $file = WP_AI_MIND_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
        if ( file_exists( $file ) ) { require_once $file; }
    }
}
