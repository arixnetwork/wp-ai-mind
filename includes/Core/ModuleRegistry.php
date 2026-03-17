<?php
declare( strict_types=1 );
namespace WP_AI_Mind\Core;

class ModuleRegistry {

    private const OPTION_KEY = 'wp_ai_mind_modules';

    /** Modules that are on by default (no user action required). */
    private const DEFAULTS = [
        'chat'            => true,
        'text_rewrite'    => true,
        'summaries'       => true,
        'seo'             => false,
        'images'          => false,
        'generator'       => true,
        'frontend_widget' => false,
        'usage'           => true,
    ];

    private array $state;

    public function __construct() {
        $saved       = get_option( self::OPTION_KEY, [] );
        $this->state = array_merge( self::DEFAULTS, (array) $saved );
    }

    public function is_enabled( string $module ): bool {
        return (bool) ( $this->state[ $module ] ?? false );
    }

    public function get_all(): array {
        return $this->state;
    }

    public function set( string $module, bool $enabled ): void {
        $this->state[ $module ] = $enabled;
        update_option( self::OPTION_KEY, $this->state );
    }
}
