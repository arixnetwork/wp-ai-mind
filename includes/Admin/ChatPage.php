<?php
declare( strict_types=1 );
namespace WP_AI_Mind\Admin;

class ChatPage {

    public static function render(): void {
        // Temporary placeholder enqueue — replaced in P3 Task 3 with full
        // wp_localize_script data, nonce, and dependency management.
        wp_enqueue_script(
            'wp-ai-mind-admin',
            WP_AI_MIND_URL . 'assets/admin/index.js',
            [ 'wp-element', 'wp-i18n' ],
            WP_AI_MIND_VERSION,
            true
        );
        wp_enqueue_style(
            'wp-ai-mind-admin',
            WP_AI_MIND_URL . 'assets/admin/index.css',
            [],
            WP_AI_MIND_VERSION
        );
        echo '<div id="wp-ai-mind-chat" class="wp-ai-mind-page"></div>';
    }
}
