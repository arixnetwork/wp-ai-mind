<?php
// includes/Admin/ChatPage.php
declare( strict_types=1 );
namespace WP_AI_Mind\Admin;

class ChatPage {

    public static function render(): void {
        self::enqueue_assets();
        echo '<div id="wp-ai-mind-chat" class="wp-ai-mind-page"></div>';
    }

    private static function enqueue_assets(): void {
        $asset_file = WP_AI_MIND_DIR . 'assets/admin/index.asset.php';
        $asset      = file_exists( $asset_file )
            ? require $asset_file
            : [ 'dependencies' => [], 'version' => WP_AI_MIND_VERSION ];

        wp_enqueue_script(
            'wp-ai-mind-admin',
            WP_AI_MIND_URL . 'assets/admin/index.js',
            array_merge( $asset['dependencies'], [ 'wp-element', 'wp-i18n', 'wp-api-fetch' ] ),
            $asset['version'],
            true
        );

        wp_localize_script( 'wp-ai-mind-admin', 'wpAiMindData', [
            'nonce'         => wp_create_nonce( 'wp_rest' ),
            'restUrl'       => esc_url_raw( rest_url( 'wp-ai-mind/v1' ) ),
            'currentPostId' => 0,
            'isPro'         => \wp_ai_mind_is_pro(),
            'siteTitle'     => get_bloginfo( 'name' ),
        ] );

        wp_enqueue_style(
            'wp-ai-mind-admin',
            WP_AI_MIND_URL . 'assets/admin/index.css',
            [],
            $asset['version']
        );
    }
}
